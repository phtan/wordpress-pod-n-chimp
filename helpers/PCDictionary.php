<?php

require_once(dirname(dirname(__FILE__)) . '/lib/KLogger.php');

$dictLog = KLogger::instance(dirname(__FILE__) . '/dictionary_logs', KLogger::DEBUG);

class PCDictionary {

	/**
	 * Converts the names of MailChimp fields to their Pods equivalent.
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
		global $pc_grouping_organization1, $pc_grouping_organization2, $pc_grouping_organization3;

		$podsFields = array();

		$mcEssentials = self::truncateMailChimpRequest($mailChimpPostRequest);

		if (!empty($mcEssentials)) {

			// Make necessary renames.
			
			foreach($mcEssentials as $key => $value) {

				// TODO replace with associative array implementation of a dictionary.
				switch ($key) {

					case 'FNAME':
						$podsFields['first_name'] = $value; // Hardcode the handles from this line and the one above like this, for now.
						break;

					case'LNAME':
						$podsFields['last_name'] = $value;
						break;

					case $pc_grouping_countriesOfInterest:
						$podsFields['countries_of_interest'] = $value;
						break;

					case $pc_grouping_organization1:
						$podsFields['organization'] = $value;
						break;

					case $pc_grouping_organization2:
						$podsFields['organization2'] = $value;
						break;

					case $pc_grouping_organization3:
						$podsFields['organization3'] = $value;
						break;

					default:
						// Do nothing.
				}
			}

			return $podsFields;

		} else {
			throw new Exception(__CLASS__ . "> Error in parsing post request. Parse result unexpectedly empty.");
		}

	}

	/**
	 * Converts Pods fields corresponding to MailChimp Interest Groupings
	 * to their MailChimp equivalents.
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
	 * Extracts information relevant to Pods from a MailChimp webhook callback.
	 * 
	 * @param  array $mailChimpPayload
	 * 	The payload from the POST request sent by the MailChimp webhook.
	 * 	As of 15 August 2013, the payload would be $thePostRequest['data'].
	 * 
	 * @return array
	 * 	A flat array of data useful to Pods.
	 */
	private function truncateMailChimpRequest($mailChimpPayload) {
		
		$flattenedRequest = array();

		// TODO defensive code here.
		
		// Decide what to keep.
		$relevantKeys = array('id', 'email', 'merges', 'list_id');
		$relevantMerges = array('FNAME', 'LNAME', 'GROUPINGS');

		// debug.
		global $dictLog;
		$dictLog->logInfo("From dict> Flattening. Dropping un-needed details...");
		// end debug.
		
		foreach ($mailChimpPayload as $field => $value) {

			foreach ($relevantKeys as $key) { // First filter.

				if ($field == $key) {

					if ($field != 'merges') {

						$flattenedRequest[$field] = $value;

					} else {

						foreach ($relevantMerges as $mergeTag) { // Second filter.

							foreach ($value as $mergeTagKey => $mergeTagValue) { // Enter sub-array.

								if ($mergeTagKey == $mergeTag) {

									if ($mergeTagKey != 'GROUPINGS') { // Third filter

										$flattenedRequest[$mergeTagKey] = $mergeTagValue;

									} else {

										foreach ($mergeTagValue as $groupingDetails) { // Enter array again.

											$flattenedRequest[$groupingDetails['name']] = $groupingDetails['groups'];
										}
									}

								}

							}

						}

					}

				} 
			}
		}

		$dictLog->logInfo("From dict> Flattened:", $flattenedRequest); // end debug.

		return $flattenedRequest;
	}

}

?>