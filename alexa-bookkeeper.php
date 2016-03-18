<?php

class AlexaBookkeeper {

	/**
	 * The relative path to your Mint credentials. A leading
	 * slash will be prepended to this string, and the whole
	 * thing will be appended to a dirname( __FILE__ ) call.
	 *
	 * @var public
	 */
	public $relativePathToMintCreds = '../../.mint.json';

	/**
	 * Handle requests here
	 */
	public function __construct()
	{
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' )
			$this->handlePost();
		else
			exit;
	}

	/**
	 * Create a speakable balance string
	 *
	 * @param float|int $balance
	 *
	 * @return string
	 */
	private function speakableBalance( $balance )
	{
		$balance = number_format( $balance, 2 );
		$balance = explode( '.', strval( $balance ) );

		$dollars = explode( ',', $balance[0] );

		if ( isset( $dollars[1] ) )
			$text = $dollars[0] . ' thousand ' . intval( $dollars[1] ) . ' dollars';
		else
			$text = $balance[0] . ' dollars';

		if ( isset( $balance[1] ) && intval( $balance[1] ) !== 0 )
			$text .= ' and ' . intval( $balance[1] ) . ' cents';

		return $text;
	}

	/**
	 * Generate a speakable name for the account. Defaults
	 * to your Mint-set name, if it's set. Otherwise it's
	 * constructed from some of Mint's internal sources.
	 *
	 * @param array $account
	 *
	 * @return string
	 */
	private function speakableName( $account ) {
		return $account['userName'] ? $account['userName'] : $account['fiLoginDisplayName'] . ' ' . $account['yodleeName'];
	}

	/**
	 * Generate a speakable sentence for the account.
	 * Uses speakableName() and speakableBalance(), plus
	 * conditionals based on the account type.
	 *
	 * @param array $account
	 *
	 * @return string
	 */
	private function speakableSentence( $account ) {
		$name    = $this->speakableName( $account );
		$balance = $this->speakableBalance( $account['currentBalance'] );

		if ( $account['accountType'] === 'credit' || $account['accountType'] === 'loan' ) {
			if ( $account['currentBalance'] == 0 ) {
				$content = "You owe nothing on your {$name} account.";
			} else {
				$content = "You owe {$balance} on your {$name} account.";
			}
		} else if ( $account['accountType'] == 'bank' ) {
			if ( $account['currentBalance'] == 0 ) {
				$content = "Your {$name} account is empty.";
			} else {
				$content = "Your {$name} account balance is {$balance}.";
			}
		}

		return $content;
	}

	/**
	 * Map a keyword from the Alexa request to our
	 * account IDs.
	 *
	 * TODO: Make this work seamlessly without needing to
	 * manually map keywords and IDs.
	 *
	 * @param string $keyword
	 *
	 * @return int|null
	 */
	function searchAccounts( $keyword ) {
		$map = [
			8338871 => [
				'schwab',
				'charles schwab',
				'schwab bank',
				'schwab checking',
				'checking',
			],
			8338909 => [
				'skymiles',
				'delta',
				'gold skymiles',
				'delta gold',
			],
			8338910 => [
				'platinum',
				'amex platinum',
			],
			8338911 => [
				'starwood',
				'spg',
				's.p.g',
				'starwood preferred guest',
			],
			8338997 => [
				'alaska airlines',
				'alaska',
				'bank of america',
			],
		];

		$keyword = strtolower( trim( $keyword ) );

		foreach( $map as $id => $aliases ) {
			if ( array_search( $keyword, $aliases ) !== false )
				return $id;
		}

		return null;
	}

	/**
	 * Get the account for the requested ID
	 *
	 * @param int $accountId
	 * @param array $accounts
	 *
	 * @return array|null
	 */
	function getAccount( $accountId, $accounts = [] ) {
		if ( empty( $accounts ) )
			$accounts = $this->accounts;

		foreach ( $accounts as $account ) {
			if ( $account['id'] === $accountId ) {
				return $account;
			}
		}

		return null;
	}

	/**
	 * Get the Mint credentials from our JSON file
	 *
	 * @return array;
	 */
	public function getMintCredentials()
	{
		$credentials = file_get_contents( dirname( __FILE__ ) . '/' . $this->relativePathToMintCreds );
		$credentials = json_decode( $credentials, true );

		return $credentials;
	}

	/**
	 * Fetch the accounts from the Python mintapi utility
	 *
	 * @return array
	 */
	public function fetchAccounts()
	{
		$credentials = $this->getMintCredentials();

		// Fetch all the accounts
		// TODO cache this
		$accounts = shell_exec( '~/pythonenv/bin/mintapi --accounts "' . $credentials['email'] . '" "' . $credentials['password'] . '"' );
		$accounts = json_decode( $accounts, true );

		return $accounts;
	}

	/**
	 * Get the decoded POST data from php://input
	 *
	 * @return array
	 */
	public function getRequest()
	{
		// Get the POST request data
		$request = file_get_contents( 'php://input' );
		$request = json_decode( $request, true );

		return $request;
	}

	/**
	 * Parse a keyword from the Alexa request
	 *
	 * @param array $request
	 *
	 * @return string
	 */
	public function getKeywordFromRequest( $request )
	{
		if ( $request === null )
			$request = $this->getRequest();

		// Get the requested account
		$keyword = $request['request']['intent']['slots']['Account']['value'];

		return $keyword;
	}

	/**
	 * Put it all together and handle any POST events
	 *
	 * @return void
	 */
	public function handlePost()
	{
		// Fetch all accounts from Mint
		$this->accounts = $this->fetchAccounts();

		// Get the requested keyword
		$keyword = $this->getKeywordFromRequest();

		// Find the account ID for the given keyword
		$accountId = $this->searchAccounts( $keyword );

		// Fetch the account for the given ID
		$account = $this->getAccount( $accountId );

		// Format a response for Alexa
		$response = $this->formatResponse( $account );

		// Actually send the request
		header( 'Content-Type: application/json' );
		echo json_encode( $response );
		exit;
	}

	/**
	 * Format a response to return to Alexa
	 *
	 * @param array $account
	 */
	public function formatResponse( $account = [] )
	{
		if ( empty( $account ) )
			throw new Exception;

		$response = [
			"response" => [
				"outputSpeech" => [
					"type" => "PlainText",
					"text" => $this->speakableSentence( $account ),
				],
				"shouldEndSession" => true,
			],
		];

		return $response;
	}

}

new AlexaBookkeeper;
