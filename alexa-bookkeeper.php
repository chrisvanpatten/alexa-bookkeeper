<?php

class AlexaBookkeeper {

	/**
	 * The absolute path to your Mint credentials.
	 *
	 * @var public
	 */
	public $pathToMintCreds;

	/**
	 * The absolute path to your cache.
	 *
	 * @var public
	 */
	public $pathToCache;

	/**
	 * The path to the Mint API binary
	 *
	 * @var public
	 */
	public $pathToMintapiBinary;

	/**
	 * Handle requests here
	 */
	public function __construct()
	{
		// Set up our initial config vars
		$this->getConfig();

		// Get our Mint credentials
		$this->getMintCredentials();

		// Only respond if a POST request
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {

			// Handle the request
			$this->handlePost();

		} else if ( $_SERVER['REQUEST_METHOD'] === 'GET' ) {

			// Handle the request
			$this->handleGet();

		}

		exit;
	}

	/**
	 * Get the config values for the session
	 */
	public function getConfig()
	{
		$config = file_get_contents( dirname( __FILE__ ) . '/.config.json' );
		$config = json_decode( $config, true );

		// Set our config vars
		$this->pathToMintapiBinary = $config['pathToMintapiBinary'];
		$this->pathToMintCreds     = $config['pathToMintCreds'];
		$this->pathToCache         = $config['pathToCache'];
	}

	/**
	 * Get the Mint credentials from our JSON file
	 *
	 * @return array;
	 */
	public function getMintCredentials()
	{
		$credentials = file_get_contents( $this->pathToMintCreds );

		$this->credentials = json_decode( $credentials, true );
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
	private function speakableName( $account )
	{
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
	private function speakableSentence( $account )
	{
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
	 * Get the account for the requested ID
	 *
	 * @param int $accountId
	 * @param array $accounts
	 *
	 * @return array|null
	 */
	function getAccount( $accountId, $accounts = [] )
	{
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
	 * Fetch the accounts from the Python mintapi utility
	 *
	 * @return array
	 */
	public function fetchAccounts()
	{
		// Set the path to the accounts cache file
		$file_accounts = $this->pathToCache . '/accounts-' . md5( $this->credentials['email'] ) . '.json';

		// If the cache is less than one hour old, reuse it
		if ( file_exists( $file_accounts ) && filemtime( $file_accounts ) > ( time() - 60 * 60 ) ) {
			$accounts = file_get_contents( $file_accounts );
		} else {
			// Otherwise, the cache is stale and should be updated
			$accounts = shell_exec( $this->pathToMintapiBinary . ' --accounts ' . $this->credentials['email'] . ' "' . $this->credentials['password'] . '" --session=' . $this->credentials['session'] );
			file_put_contents( $file_accounts, $accounts, LOCK_EX );
		}

		// Decode the accounts
		$accounts = json_decode( $accounts, true );

		// Return the array
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
	public function getKeywordFromRequest( $request = null )
	{
		if ( $request === null )
			$request = $this->getRequest();

		// Get the requested account
		$keyword = $request['request']['intent']['slots']['Account']['value'];

		return $keyword;
	}

	/**
	 * Build the search index for our accounts
	 *
	 * @return array
	 */
	public function buildIndex()
	{
		// Loop through accounts
		foreach ( $this->accounts as $account ) {
			// Skip "ignore" accounts
			if ( $account['userName'] == 'ignore' )
				continue;

			// Start with a fresh corpus
			$corpus = [];

			// The keys we want to add to our corpus
			$keys = [
				'fiLoginDisplayName',
				'userName',
				'accountName',
				'yodleeName',
				'fiName',
			];

			// Add each value to our corpus for this account
			foreach ( $keys as $key ) {
				if ( isset( $account[ $key ] ) )
					$corpus[] = $account[ $key ];
			}

			// Remove duplicate words, punctuation, and lowercase it all
			$corpus = implode( ' ', $corpus );
			$corpus = explode( ' ', $corpus );
			$corpus = array_unique( $corpus );
			$corpus = implode( ' ', $corpus );
			$corpus = trim( preg_replace( "/[^0-9a-z]+/i", " ", $corpus ) );
			$corpus = strtolower( $corpus );

			// Add to the index
			$index[ $account['id'] ] = $corpus;
		}

		// Return the full index
		return $index;
	}

	/**
	 * Get the closest matching account ID for the search term
	 *
	 * @param string $search
	 *
	 * @return int
	 */
	function searchAccounts( $search )
	{
		$results = [];

		// Lowercase the search term
		$search = strtolower( $search );

		// Build the search index
		$index = $this->buildIndex();

		// Get similarity ratings for each item in the index
		foreach( $index as $id => $corpus ) {
			$results[ $id ] = similar_text( $search, $corpus );
		}

		// Sort the results by highest to lowest match
		arsort( $results );

		$resultsSorted = [];

		// Rebuild the sorted array of IDs
		foreach ( $results as $id => $similarity ) {
			$resultsSorted[] = $id;
		}

		// Return the first ID in the bunch
		return $resultsSorted[0];
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

	public function handleGet()
	{
		// Fetch accounts
		$this->accounts = $this->fetchAccounts();

		// Actually send the request
		header( 'Content-Type: application/json' );
		echo json_encode( $this->accounts );
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
