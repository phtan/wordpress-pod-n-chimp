<?php

require_once(dirname(dirname(__FILE__)) . '/lib/KLogger.php');

$dictLog = KLogger::instance(dirname(__FILE__) . '/dictionary_logs', KLogger::DEBUG);

class PCDictionary {

	/**
	 * Converts the names of interest groupings in MailChimp to their equivalent
	 * as Pods fields.
	 * 
	 * @throws Exception
	 * 	If there is an error in conversion.
	 */
	public function convertToPodsFields($mailChimpPostRequest) {
		
		// debug.
		global $dictLog;
		$dictLog->logInfo('Request is: ', $mailChimpPostRequest);

		$podsFields = array();

		$groupings = self::parseInterestGroupings($mailChimpPostRequest);

		if (!empty($groupings)) {

			foreach($groupings as $group => $value) {

				// Hardcode here for now.
				// TODO replace with associative array implementation of a dictionary.
				if ($group == 'countries_of_interest') {
					$podsFields['countries_of_interest'] = $value;
				} if ($group == 'organization') {
					$podsFields['organization'] = $value;
				} if ($group == 'organization2') {
					$podsFields['organization2'] = $value;
				} if ($group == 'organization3') {
					$podsFields['organization3'] = $value;
				} if ($group == 'position') {
					$podsFields['position'] = $value;
				} if ($group == 'position2') {
					$podsFields['position2'] = $value;
				} if ($group == 'position3') {
					$podsFields['position3'] = $value;
				} else {
					$podsFields[$group] = $value; // copy as is, without converting.
				}
			}

			return $podsFields;

		} else {
			throw new Exception("Post request cannot be parsed.");
		}

	}

	private function parseInterestGroupings($mailChimpPostRequest) {
		

		$interestGroupings = array();

		// TODO defensive code here.

		// debug.
		global $dictLog;
		$dictLog->logInfo("From dict> Parsing these groupings: ", $mailChimpPostRequest['merges']['GROUPINGS']);

		foreach ($mailChimpPostRequest['merges']['GROUPINGS'] as $grouping) {
			$groupingName = $grouping['name'];
			$interestGroupings[$groupingName] = $grouping['groups'];
		}

		return $interestGroupings;
	}

}

?>