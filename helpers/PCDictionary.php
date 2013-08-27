<?php

require_once(dirname(dirname(__FILE__)) . '/lib/KLogger.php');

$dictLog = KLogger::instance(dirname(__FILE__) . '/dictionary_logs', KLogger::DEBUG);

class PCDictionary {

	/**
	 * Converts the names of MailChimp fields to their Pods equivalent.
	 * 
	 * @return array
	 *  The renamed fields.
	 *
	 * @throws Exception
	 * 	If there is an error in conversion.
	 */
	public static function convertToPodsFields($mailChimpPostRequest) {

		// debug.
		global $dictLog;
		$dictLog->logInfo('Request is: ', $mailChimpPostRequest);
		// end debug.

		$podsFields = array();

		$mcEssentials = self::truncateMailChimpRequest($mailChimpPostRequest);

		if (!empty($mcEssentials)) {

			try {
				$mappings = self::buildMappingFromMCToPods();
			} catch (Exception $e) {
				throw $e;
			}

			// Make necessary renames.			
			foreach($mcEssentials as $key => $value) {

				if(isset($mappings[$key])) {

					$podsEquivalent = $mappings[$key];
					$podsFields[$podsEquivalent] = $value;

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

			global $pc_podsFields; // Exhaustive mapping of Pods field names to MailChimp groupings.

			foreach($pc_podsFields as $podsField => $chimpGrouping) {

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

		global $pc_relevantKeys, $pc_relevantMergeTags; // Decides what to keep from the payload.

		// debug.
		global $dictLog;
		$dictLog->logInfo("From dict> Flattening. Dropping un-needed details...");
		// end debug.
		
		foreach ($mailChimpPayload as $field => $value) {

			foreach ($pc_relevantKeys as $key) { // First filter.

				if ($field == $key) {

					if ($field != 'merges') {

						$flattenedRequest[$field] = $value;

					} else {

						foreach ($pc_relevantMergeTags as $mergeTag) { // Second filter.

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

	/*
	 * Builds a reference for the Pods equivalent for specific MailChimp
	 * variables.
	 *
	 * @return array
	 *  An associative array with the MailChimp variable as the key
	 *  and the Pods equivalent as the value.
	 *
	 * @throws Exception
	 *  If there is an error in obtaining the information needed,
	 *  or in building the array.
	 */
	private function buildMappingFromMCToPods() {

		global $pc_podsFields, $pc_mergeTags;
		$map = array();

		$groupingsIsToFields = array_flip($pc_podsFields); // Expected result: array('chimpInterestGroup' => 'podsFieldName', ...)
		
		if ($groupingsIsToFields == null) {

			throw new Exception (__CLASS__ . " > Cannot map MailChimp names to Pods names. Error with flipping \$pc_podsFields (defined in config.php)");

		} else {

			$map = array_merge($groupingsIsToFields, $pc_mergeTags);
		}

		return $map;
	}

}

?>