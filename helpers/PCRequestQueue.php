<?php

/**
 * This file is part of the Pod 'n Chimp plug-in. It implements a queue to
 * store information on incoming callbacks from the MailChimp webhook.
 *
 * This queue allows Pods to tell whether a save to a Pods item originated
 * from MailChimp, or directly from the Pods interface in the WordPress
 * dashboard. 
 */

require_once(dirname(dirname(__FILE__)) . '/lib/KLogger.php');

class PCRequestQueue {

	private static $instance;
	private static $queue = array();

	private static $timeKey = 'time';
	private static $processedKey = 'processed';
	private static $dateFormat = DateTime::ISO8601;

	const DONE = 'true';
	const NOT_DONE = 'false';

	private function __construct() {
		
		date_default_timezone_set('Asia/Singapore');
	}	
	
	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new self;
		}

		self::log("Serving an instance of " . __CLASS__ . "...");
		return self::$instance;
	}

	/**
	 * Adds the email to the queue as an unprocessed job.
	 * @param string $email
	 */
	public function add($email) {

		self::log(__METHOD__ . "> Adding $email to queue...");

		$now = new DateTime();
		$nowFormatted = $now->format(self::$dateFormat);
		$meta = array(
			self::$timeKey => $nowFormatted,
			self::$processedKey => self::NOT_DONE
			);

		// Each email points to an array of arrays holding info on each
		// request.
		if (!is_array(self::$queue[$email])) {
			self::$queue[$email] = array();
		}

		array_push(self::$queue[$email], $meta);

		// debug.
		self::log(__CLASS__ . "> Added $email to the queue.");
		self::log(__CLASS__ . "> Queue is now:", self::$queue);
	}

	/**
	 * Checks the queue for unprocessed jobs for the specified email.
	 * @param  string  $email
	 *  The email address to check for unprocessed jobs for.
	 * 
	 * @param  integer $searchInterval
	 *  Optional argument. The time range (from now) to search unprocessed jobs for.
	 *  
	 * @return boolean
	 *  True if there is an unprocessed job within the time range.
	 */
	public function isInQueue($email, $searchInterval = 5) { // default interval is 1 to account for the time lapsed during the loop.
		
		$result = false;

		if (self::$queue != null){
			if (isset(self::$queue[$email])) {

				foreach(self::$queue[$email] as $request) {
					if (isset($request[self::$timeKey]) &&
							isset($request[self::$processedKey])) {
						
						if (self::isWithinInterval($searchInterval, $request[self::$timeKey])) {

							if ($request[self::$processedKey] == self::NOT_DONE) {

								$result = true;
								break;
							}

						}
					}
				}
			}
		}

		// debug
		if ($result) {
			self::log(__METHOD__ . " > $email is in queue.");	
		} else {
			self::log(__METHOD__ . " > $email is not in queue.");	
		}
		
		return $result;
	}

	/**
	 * Marks the job for the given email as done.
	 * @param  string $email
	 * 
	 * @param  integer $interval
	 *  Optional argument. The time interval from now, in seconds, within which
	 *  to mark jobs as done.
	 *  
	 * @return int
	 *  The number of jobs that have been marked.
	 */
	public function markDone($email, $interval = 5) {
			
		$marked = self::mark(true, $email, $interval);

		// debug
		self::log(__CLASS__ . "> Queue is now:", self::$queue);
		// end debug.

		return $marked;
	}

	/**
	 * Marks the job for the given email as not done.
	 * 
	 * @param  string $email
	 * 
	 * @param  integer $interval
	 *  Optional argument. The time interval from now, in seconds, within which
	 *  to mark jobs as not done.
	 *  
	 * @return int
	 *  The number of jobs that have been marked.
	 */
	public function markNotDone($email) {

		$marked = self::mark(false, $email,$interval);
		return $marked;
		
	}

	/**
	 * Marks the job in the queue for the specified email as done or not done.
	 * 
	 * @param  bool $isDone
	 *  Whether to mark as done, or not.
	 *  
	 * @param  string $email
	 * 
	 * @param  int $interval
	 *  The time interval from now, in seconds, within which
	 *  to mark the job(s) for the given address.
	 *  
	 * @return int
	 *  The number of jobs that have been marked.
	 */
	private function mark($isDone, $email, $interval) {

		$markedJobs = 0;
		$marker = self::DONE;

		if (!$isDone) {
			$marker = self::NOT_DONE;
		}

		if (isset(self::$queue[$email]) && is_array(self::$queue[$email])) {
			
			foreach(self::$queue[$email] as &$request) {

				if (isset($request[self::$timeKey]) &&
					self::isWithinInterval($interval, $request[self::$timeKey])) {

						$request[self::$processedKey] = $marker;						
						$markedJobs++;
					}
			}

			unset($request); // Remove the variable passed to foreach as reference.

		} else {
			// TODO throw exception here.
		}

		return $markedJobs;
	}

	/**
	 * Checks if the given time is within the specified interval
	 * from the current time, inclusive.
	 *
	 * @param integer $interval
	 *  The interval, in seconds.
	 *
	 * @param string $time
	 *  The time to check for. It must be formatted
	 *  according to the format in the private variable
	 *  $dateFormat.
	 *  Labelled Assumption (1) in the code below.
	 * 
	 * @return boolean
	 *  True if the given time falls within the given interval from
	 *  the current time.
	 */
	private function isWithinInterval($interval, $time) {
		
		$timeOfRequest = DateTime::createFromFormat(self::$dateFormat, $time); // Assumption (1).

		$now = new DateTime();
		$isAbsolute = true;
		$absoluteDifference = $now->diff($timeOfRequest, $isAbsolute);

		return ($absoluteDifference->y == 0 &&
				$absoluteDifference->m == 0 &&
				$absoluteDifference->d == 0 &&
				$absoluteDifference->h == 0 &&
				$absoluteDifference->i == 0 && // minute
				(($absoluteDifference->s <= $interval)) // second
				);
	}

	/**
	 * Logs a message.
	 */
	private function log($msg, $obj = null) {

		if(!$obj) {
				$obj = KLogger::NO_ARGUMENTS;
		}

		$logger = KLogger::instance(dirname(__FILE__) . '/queue_logs', KLogger::DEBUG);
		$logger->logInfo($msg, $obj);
	}

}

?>