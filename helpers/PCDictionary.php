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
	public static function convertToPodsFields($mailChimpPostRequest) {

		// debug.
		global $dictLog;
		$dictLog->logInfo('Request is: ', $mailChimpPostRequest);
		// end debug.

		global $pc_grouping_countriesOfInterest;

		$podsFields = array();

		$groupings = self::parseInterestGroupings($mailChimpPostRequest);

		if (!empty($groupings)) {

			foreach($groupings as $group => $value) {

				// TODO replace with associative array implementation of a dictionary.
				if ($group == $pc_grouping_countriesOfInterest) {
					$podsFields['countries_of_interest'] = $value; // TODO hardcode the key like this for now.

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

	/**
	 * Converts Pods fields that correspond to MailChimp Interest Groupings,
	 * to their MailChimp names.
	 *
	 * @param array $fields
	 * 	An array of details for the Pods fields of an item. This should be provided
	 * 	by Pods.
	 *
	 * @return array
	 * 	An array meant for use as the value to the merge_vars['groupings'] key.
	 * 	A description of this array can be found
	 * 	{@link http://apidocs.mailchimp.com/api/2.0/lists/subscribe.php here}.
	 * 
	 * @throws Exception
	 * 	If the input array is empty.
	 */
	public static function getMailChimpGroupings($fields) {
		
		// debug.
		global $dictLog;
		$dictLog->logInfo(__CLASS__ . "> Converting these for MailChimp:",
			$fields);

		$groupings = array();

		if (!empty($fields)) {

			// Exhaustively enumerate the mapping.
			global $pc_grouping_countriesOfInterest, $pc_grouping_organizations;
			
			$relevantFields = array(

				'countries_of_interest' => $pc_grouping_countriesOfInterest,
				'organization1' => $pc_grouping_organization1,
				'organization2' => $pc_grouping_organization2,
				'organization3' => $pc_grouping_organization3,
				// Another Pods field => Associated MailChimp grouping

				);

			foreach($relevantFields as $podsField => $chimpGrouping) {

				if (isset($fields[$podsField])) {
					
					$groupings[] = array( // append this sub-array to $groupings

						// the names of the keys below are specified according
						// to the "merge_vars --> groupings" parameter at
						// {@link http://apidocs.mailchimp.com/api/2.0/lists/subscribe.php}.

						'name' => $chimpGrouping,
						'groups' => array(
							$fields[$podsFields]['value'] // TODO check if Pods stores the the value to the key "value", as an array instead of the expected single string, when there multiple values.
							) 
						); 
				}

			}

			return $groupings;

		} else {
			throw new Exception("Cannot convert from Pods to MailChimp. Data from Pods is empty.");
		}
	}

	/**
	 * Private helper method.
	 * 
	 * @param  array $mailChimpPostRequest
	 * 	The payload from the POST request sent by the MailChimp webhook.
	 * 
	 * @return array
	 * 	The Interest Groupings from MailChimp, converted to their Pods
	 * 	equivalents.
	 */
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