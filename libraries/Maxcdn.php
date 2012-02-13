<?php

/**
 * MaxCDN
 * Integrate with the MaxCDN XML RPC library.
 * Note: I modified things to work with CI XML-RPC and removed ridiculous date handling. - Phil
 * 
 * @author Jayson J. Phillips <jayson.phillips@chroniumlabs.com>
 * @author Phil Sturgeon
 * @copyright Copyright (c) 2011 Chronium Labs LLC
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @version 1.0.0
 * @package CodeIgniter\Libraries
 *
 */

/**
 * Custom Exceptions for the CloudFiles API
 * @package chroniumlabs.maxcdn-api.exceptions
 */
class SyntaxException extends Exception {}
class VariableTypeException extends Exception {}
class MissingRequirementException extends Exception {}

class Maxcdn
{
	var $api_key;
	var $user_id;
	var $xml_rpc_options = array();
	var $base_url = "api.netdna.com/xmlrpc/";
	private $auth_string;

	/**
	 * Constructor
	 *
	 * @param string $api_key
	 * @param string $user_id
	 */
	public function __construct($params = array())
	{
		$this->ci = get_instance();
		
		// This is some fucking ridiculous code in the original example. 
		// date_default_timezone_set('America/Los_Angeles');
		
		// Below is code taken from a 2008 tutorial that doesnt break your whole application
		$this->current_date = new DateTime("now", new DateTimeZone('America/Los_Angeles'));
		
		$this->ci->load->library('xmlrpc');
		
		// $this->ci->xmlrpc->set_debug();
		
		isset($params['maxcdn_api_key']) and $this->api_key = $params['maxcdn_api_key'];
		isset($params['maxcdn_user_id']) and $this->user_id = $params['maxcdn_user_id'];
	}

	// Utility functions for setting up the pieces for transmitting data. 
	// TODO: Separate into separate file and add as a require

	/**
	 * The MaxCDN XMLRPC API Class
	 * @package chroniumlabs.maxcdn-api.utilities
	 * @subpackage utilities
	 */

	/**
	 * Set Auth String
	 * Required for every call. Not in constructor so that the date (in ISO8601 format) is always fresh when called.
	 * 
	 * @param string $method
	 * @return string sha-256 hash for authstring
	 */
	public function setAuthString($method)
	{
		return hash('sha256', $this->current_date->format('c') . ':' . $this->api_key . ':' . $method);
	}

	/**
	 * Send Request
	 * sendRequest
	 * Uses params internally to obtain a proper xmlrpcmsg to transmit
	 * Returns a reference to the return of the xmlrpc_client->send method 
	 * 
	 * @param string $namespace
	 * @param string $method
	 * @param array $params
	 * @return object $result
	 */
	public function sendRequest($namespace, $method, $params = array())
	{
		$this->ci->xmlrpc->server('http11://'.$this->base_url.$namespace, 80);
		$this->ci->xmlrpc->method($namespace.'.'.$method);
		
		$this->ci->xmlrpc->request(array_merge(array(
			$this->user_id,
			$this->setAuthString($method),
			$this->current_date->format('c'),
		), $params));
		
		if ( ! ($foo = $this->ci->xmlrpc->send_request()))
		{
			show_error($this->ci->xmlrpc->display_error());
		}
		
		return $this->ci->xmlrpc->display_response();
	}

	/**
	 * Account Methods
	 * @subpackage account
	 */

	/**
	 * Get Bandwidth
	 * getBandwidth
	 * Takes optional parameters $from & $to in Y-m-d format
	 * Example: $this->getBandwidth('2011-05-22', '2011-05-23');
	 * 
	 * @param string $form (optional)
	 * @param string $to (optional)
	 * @return object $xmlrpcresp 
	 */
	public function getBandwidth($from = null, $to = null)
	{
		return $this->sendRequest('account', 'getBandwidth', array($from, $to));
	}

	/**
	 * Reporting methods
	 * @subpackage report
	 */
	public function getTotalTransfer($zone_id, $type, $from = null, $to = null, $timezone = null)
	{
		if (empty($zone_id) || empty($type))
		{
			throw new MissingRequirementException('One or more required parameters are empty');
		}
		return $this->sendRequest('report', 'getTotalTransfer', array($zone_id, $type, $from, $to, $timezone));
	}

	public function getTotalHits($zone_id, $type, $from = null, $to = null, $timezone = null)
	{
		if (empty($zone_id) || empty($type))
		{
			throw new MissingRequirementException('One or more required parameters are empty');
		}
		return $this->sendRequest('report', 'getTotalHits', array($zone_id, $type, $from, $to, $timezone));
	}

	/**
	 * Get Total Stats
	 * Returns transfer stats for a given company/zone and date range
	 * Required: $company_id, $date_from (Y-m-d), $date_to (Y-m-d), $zone_id
	 * Optional: $sort_by (array)
	 * 			 $view_by (either "hourly" or "daily")
	 * 			 $maximum (number of records returned) 
	 * 			 $offset 
	 * 			 $timezone ("America/New_York")
	 * 
	 * Example:	$this->getTotalStats('company_id', '2011-05-23', '2011-05-28', 'zone-id');
	 * 
	 * @param mixed $company_id - Unique company_id or alias
	 * @param string $date_from - Start Date
	 * @param string $date_to - End Date
	 * @param int $zone_id - Zone identifier
	 * @param array $sort_by - an array of "column sortorder" strings, please see returned columns for possible values. sortorder can be ASC or DESC (optional)
	 * @param string $view_by (optional)
	 * @param int $maximum - the maximum number of records to return (optional)
	 * @param int $offset (optional)
	 * @param string $timezone (optional)
	 * @return object $xmlrpcresp | array $value
	 */
	public function getTotalTransferStats($company_id, $date_from, $date_to, $zone_id, $sort_by = null, $maximum = null, $offset = null, $timezone = null, $view_by = null)
	{

		if (empty($company_id) || empty($zone_id) || empty($date_from) || empty($date_to))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}
		return $this->sendRequest('report', 'getTotalTransferStats', array($company_id, $zone_id, $date_from, $date_to, $sort_by,
				$view_by, $maximum, $offset, $timezone));
	}

	/**
	 * Get Cache Hit Statistics
	 * Returns the total cache hits for a given company/zone and date range
	 * Required: $company_id, $date_from (Y-m-d), $date_to (Y-m-d), $zone_id
	 * Optional: $sort_by (array)
	 * 			 $maximum (number of records returned) 
	 * 			 $offset 
	 * 			 $timezone ("America/New_York")
	 * 
	 * Example:	$this->getCacheHitStats('company_id', '2011-05-23', '2011-05-28', 'zone-id');
	 * 
	 * @param mixed $company_id - Unique company_id or alias
	 * @param string $date_from - Start Date
	 * @param string $date_to - End Date
	 * @param int $zone_id - Zone identifier
	 * @param array $sort_by (optional) - "column sortorder" strings. see return for possible values. sortorder can be ASC or DESC
	 * @param int $maximum - the maximum number of records to return (optional)
	 * @param int $offset (optional)
	 * @param string $timezone (optional)
	 * @return object $xmlrpcresp | int $value
	 */
	public function getCacheHitStats($company_id, $date_from, $date_to, $zone_id, $sort_by = null, $maximum = null, $offset = null, $timezone = null)
	{

		if (empty($company_id) || empty($zone_id) || empty($date_from) || empty($date_to))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}
		return $this->sendRequest('report', 'getCacheHitStats', array($company_id, $zone_id, $date_from, $date_to, $sort_by,
				$maximum, $offset, $timezone));
	}

	/**
	 * Get Popular Files
	 * Returns a list of popular files for a given company/zone and date range
	 * Required: $company_id, $date_from (Y-m-d), $date_to (Y-m-d), $zone_id
	 * Optional: $sort_by (array)
	 * 			 $maximum (number of records returned) 
	 * 			 $offset 
	 * 			 $timezone ("America/New_York")
	 * 
	 * Example:	$this->getPopularFiles('company_id', '2011-05-23', '2011-05-28', 'zone-id');
	 * 
	 * @param mixed $company_id - Unique company_id or alias
	 * @param string $date_from - Start Date
	 * @param string $date_to - End Date
	 * @param int $zone_id - Zone identifier
	 * @param array $sort_by - an array of "column sortorder" strings, please see returned columns for possible values. sortorder can be
	 *				ASC or DESC (optional)
	 * @param int $maximum - the maximum number of records to return (optional)
	 * @param int $offset (optional)
	 * @param string $timezone (optional)
	 * @return object $xmlrpcresp | array $value
	 */
	public function getPopularFiles($company_id, $date_from, $date_to, $zone_id, $sort_by = null, $maximum = null, $offset = null)
	{

		if (empty($company_id) || empty($zone_id) || empty($date_from) || empty($date_to))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}
		return $this->sendRequest('report', 'getPopularFiles', array($company_id, $zone_id, $date_from, $date_to, $sort_by,
				$maximum, $offset));
	}

	/**
	 * Get Usage Per Day
	 * Returns usage stats for a give company/zone and date range
	 * Required: $company_id, $date_from (Y-m-d), $date_to (Y-m-d), $zone_id
	 * Optional: $sort_by (array)
	 * 			 $maximum (number of records returned) 
	 * 			 $offset 
	 * 
	 * Example:	$this->getUsagePerDay('company_id', '2011-05-23', '2011-05-28', 'zone-id');
	 * 
	 * @param mixed $company_id - Unique company_id or alias
	 * @param string $date_from - Start Date
	 * @param string $date_to - End Date
	 * @param int $zone_id - Zone identifier
	 * @param array $sort_by - an array of "column sortorder" strings, please see returned columns for possible values. 
	 * sortorder can be ASC or DESC (optional)
	 * @param int $maximum - the maximum number of records to return (optional)
	 * @param int $offset (optional)
	 * @return object $xmlrpcresp | int $value
	 */
	public function getUsagePerDay($company_id, $date_from, $date_to, $zone_id, $sort_by = null, $maximum = null, $offset = null)
	{

		if (empty($company_id) || empty($zone_id) || empty($date_from) || empty($date_to))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}
		return $this->sendRequest('report', 'getUsagePerDay', array($company_id, $zone_id, $date_from, $date_to, $sort_by,
				$maximum, $offset));
	}

	/**
	 * Get Node Hits
	 * Returns a list of node hits for a given company/zone and date range
	 * Required: $company_id, $date_from (Y-m-d), $date_to (Y-m-d), $zone_id
	 * Optional: $sort_by (array)
	 * 			 $maximum (number of records returned) 
	 * 			 $offset 
	 * 
	 * Example:	$this->getNodeHits('company_id', '2011-05-23', '2011-05-28', 'zone-id');
	 * 
	 * @param mixed $company_id - Unique company_id or alias
	 * @param string $date_from - Start Date
	 * @param string $date_to - End Date
	 * @param int $zone_id - Zone identifier
	 * @param array $sort_by - an array of "column sortorder" strings, please see returned columns for possible values. 
	 * sortorder can be ASC or DESC (optional)
	 * @param int $maximum - the maximum number of records to return (optional)
	 * @param int $offset (optional)
	 * @return object $xmlrpcresp | array $value
	 */
	public function getNodeHits($company_id, $date_from, $date_to, $zone_id, $sort_by = null, $maximum = null, $offset = null)
	{

		if (empty($company_id) || empty($zone_id) || empty($date_from) || empty($date_to))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}
		return $this->sendRequest('report', 'getNodeHits', array($company_id, $zone_id, $date_from, $date_to, $sort_by,
				$maximum, $offset));
	}

	/**
	 * Get Connection Stats
	 * Returns a list of live zone daily connection stats for a given company/zone and date range
	 * Required: $company_id, $date_from (Y-m-d), $date_to (Y-m-d), $zone_id
	 * Optional: $sort_by (array)
	 * 			 $maximum (number of records returned) 
	 * 			 $offset 
	 * 			 $timezone
	 * 
	 * Example:	$this->getConnectionStats('company_id', '2011-05-23', '2011-05-28', 'zone-id');
	 * 
	 * @param mixed $company_id - Unique company_id or alias
	 * @param string $date_from - Start Date
	 * @param string $date_to - End Date
	 * @param int $zone_id - Zone identifier
	 * @param array $sort_by - an array of "column sortorder" strings, please see returned columns for possible values. 
	 * sortorder can be ASC or DESC (optional)
	 * @param int $maximum - the maximum number of records to return (optional)
	 * @param int $offset (optional)
	 * @param string $timezone (optional)
	 * @return object $xmlrpcresp | array $value
	 */
	public function getConnectionStats($company_id, $date_from, $date_to, $zone_id, $sort_by = null, $maximum = null, $offset = null, $timezone = null)
	{

		if (empty($company_id) || empty($zone_id) || empty($date_from) || empty($date_to))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}
		return $this->sendRequest('report', 'getConnectionStats', array($company_id, $zone_id, $date_from, $date_to, $sort_by,
				$maximum, $offset, $timezone));
	}

	/**
	 * Get Hourly Connection Stats
	 * Returns a list of live zone hourly connection stats for a given company/zone and date
	 * Required: $company_id, $date_from (Y-m-d), $zone_id
	 * Optional: $sort_by (array)
	 * 			 $maximum (number of records returned) 
	 * 			 $offset 
	 * 			 $timezone
	 * 
	 * Example:	$this->getHourlyConnectionStats('company_id', '2011-05-23', 'zone-id');
	 * 
	 * @param mixed $company_id - Unique company_id or alias
	 * @param string $date_from - The date you want to fetch hourly stats for
	 * @param int $zone_id - Zone identifier
	 * @param array $sort_by - an array of "column sortorder" strings, please see returned columns for possible values. 
	 * sortorder can be ASC or DESC (optional)
	 * @param int $maximum - the maximum number of records to return (optional)
	 * @param int $offset (optional)
	 * @param string $timezone (optional)
	 * @return object $xmlrpcresp | array $value
	 */
	public function getHourlyConnectionStats($company_id, $date_from, $zone_id, $sort_by = null, $maximum = null, $offset = null, $timezone = null)
	{

		if (empty($company_id) || empty($zone_id) || empty($date_from))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}
		return $this->sendRequest('report', 'getHourlyConnectionStats', array($company_id, $zone_id, $date_from, $sort_by,
				$maximum, $offset, $timezone));
	}

	/**
	 * User methods
	 * @subpackage users
	 */

	/**
	 * List Users
	 * user.listUsers
	 * 
	 * <code>
	 *	$this->getUserList();
	 * </code>
	 * 
	 * @return object xmlrpcresp | array $value
	 * 
	 */
	public function getUserList()
	{
		return $this->sendRequest('user', 'listUsers');
	}

	/**
	 * Update User
	 * user.update
	 * 
	 * <code>
	 *	$this->updateUser($user_id, $update_values);
	 * </code>
	 * 
	 * Required: int $user_id, struct $update_values
	 * 
	 * @param int $user_id
	 * @param array $update_values (key/value pair of settings) 
	 * @return object xmlrpcresp | array $value
	 */
	public function updateUser($user_id, $update_values)
	{
		if (empty($user_id) || empty($update_values))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}

		if (!is_array($update_values))
		{
			throw new VariableTypeException('Required parameter is expected to be an array');
		}

		return $this->sendRequest('user', 'update', array($user_id, $update_values));
	}

	/**
	 * Cache methods
	 * @subpackage cache
	 */

	/**
	 * Purge - Purge a file from cache
	 * cache.purge
	 * 
	 * <code>
	 *	$this->purgeFromCache($url);
	 * </code>
	 * 
	 * Required: $url
	 *
	 * @param string $url 
	 * @return object xmlrpcresp | boolean $value
	 * 
	 */
	function purgeFromCache($url)
	{
		if (empty($url))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}
		
		return $this->sendRequest('cache', 'purge', array($url));
	}
	
	/**
	 * Purge All Cache 
	 * Purges a file from cache so it is pulled from the origin server the next time it is requested
	 * cache.purgeAllCache
	 * 
	 * <code>
	 *	$this->purgeAllCache($zone);
	 * </code>
	 * 
	 * Required: $zone
	 *
	 * @param string $zone - name of the zone you wish to purge 
	 * @return object xmlrpcresp | boolean $value
	 * 
	 */
	public function purgeAllCache($zone)
	{
		if (empty($zone))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}
		
		$this->sendRequest('cache', 'purgeAllCache', array($zone));
	}
	
	/**
	 * Pull zone methods
	 * @subpackage pullzone
	 */

	/**
	 * Pullzone Create 
	 * Creates a new Pull Zone on the edge servers. This type of zone caches content on the edge servers and pulls from the customer’s origin server as needed.
	 * pullzone.create
	 * 
	 * <code>
	 *	$this->createPullZone($name, $origin, $vhost, $ip, $compress, $vanity_url, $label);
	 * </code>
	 * 
	 * Required: string $name, string $origin
	 *
	 * @param string $name 
	 * @param string $origin	
	 * @param string $vhost (optional)
	 * @param string $ip (optional)
	 * @param string $compress (optional)
	 * @param string $vanity_url (optional)
	 * @param string $label (optional)
	 * @return object xmlrpcresp | array $value (if failed, array(int errorCode, string errorMessage))
	 * 
	 */
	public function createPullZone($name, $origin, $vhost = null, $ip = null, $compress = null, $vanity_url = null, $label = null)
	{
		if (empty($name) || empty($origin))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}
		
		$zone_values = array($name, $origin, $vhost, $ip, $compress, $vanity_url, $label);
		$this->sendRequest('pullzone', 'create', array($zone_values));
	}

/**
	 * Pullzone Update 
	 * Updates an existing Pull Zone
	 * pullzone.update
	 * 
	 * <code>
	 *	$this->updatePullZone($id, $name, $origin, $vhost, $ip, $compress, $vanity_url, $label);
	 * </code>
	 * 
	 * Required: int $zone_id
	 *
	 * @param int			$zone_id
	 * @param string	$name (optional)
	 * @param string	$origin (optional)
	 * @param string	$vhost (optional)
	 * @param string	$ip (optional)
	 * @param string	$compress (optional)
	 * @param string	$vanity_url (optional)
	 * @param string	$label (optional)
	 * @return object xmlrpcresp | int $value (0 is success, 1 if failed)
	 * 
	 */
	public function updatePullZone($zone_id, $name = null, $origin = null, $vhost = null, $ip = null, $compress = null, $vanity_url = null, $label = null)
	{
		if (empty($zone_id))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}
	
		$zone_values = array($name, $origin, $vhost, $ip, $compress, $vanity_url, $label);
	
		$this->sendRequest('pullzone', 'update', array($zone_id, $zone_values));
	}

/**
	 * Pullzone List Zones 
	 * Lists all Pull Zones on your account
	 * pullzone.listZones
	 * 
	 * <code>
	 *	$this->getPullZones();
	 * </code>
	 * 
	 * @return object xmlrpcresp | array $value
	 * 
	 */
	public function getPullZones()
	{
		return $this->sendRequest('pullzone', 'listZones');
	}

	/**
	 * Push zone methods
	 * @subpackage pushzone
	 */

	/**
	 * Pushzone Create 
	 * Creates a new Push Zone 
	 * pushzone.create
	 * 
	 * <code>
	 *	$this->createPushZone($name, $password, $compress, $vanity_url, $label);
	 * </code>
	 * 
	 * Required: string $name, string $password
	 *
	 * @param string $name 
	 * @param string $password	
	 * @param string $compress (optional)
	 * @param string $vanity_url (optional)
	 * @param string $label (optional)
	 * @return object xmlrpcresp | array $value (if failed, array(int errorCode, string errorMessage))
	 * 
	 */
	public function createPushZone($name, $password, $compress = null, $vanity_url = null, $label = null)
	{
		if (empty($name) || empty($password))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}

		$zone_values = array($name, $password, $compress, $vanity_url, $label);
		return $this->sendRequest('pushzone', 'create', array($zone_values));
	}

/**
	 * Pushzone Update 
	 * Updates an existing Push Zone
	 * pushzone.update
	 * 
	 * <code>
	 *	$this->updatePushZone($zone_id, $name, $password, $label);
	 * </code>
	 * 
	 * Required: int $zone_id
	 *
	 * @param int			$zone_id
	 * @param string	$name (optional)
	 * @param string	$password (optional)
	 * @param string	$label (optional)
	 * @return object xmlrpcresp | int $value (0 is success, 1 if failed)
	 * 
	 */
	public function updatePushZone($zone_id, $name = null, $password = null, $label = null)
	{
		if (empty($zone_id))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}

		$zone_values = array($name, $password, $label);

		return $this->sendRequest('pushzone', 'update', array($zone_id, $zone_values));
	}

	/**
	 * Pushzone Delete 
	 * Deletes an existing Push Zone
	 * pushzone.delete
	 * 
	 * <code>
	 *	$this->deletePushZone($zone_id);
	 * </code>
	 * 
	 * Required: int $zone_id
	 *
	 * @param int			$zone_id
	 * @return object xmlrpcresp | int $value (0 is success, 1 if failed)
	 * 
	 */
	public function deletePushZone($zone_id)
	{
		if (empty($zone_id))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}

		return $this->sendRequest('pushzone', 'delete', array($zone_id));
	}

	/**
	 * Pushzone List Zones 
	 * Lists all Push Zones on your account
	 * pushzone.listZones
	 * 
	 * <code>
	 *	$this->getPushZones();
	 * </code>
	 * 
	 * @return object xmlrpcresp | array $value
	 * 
	 */
	public function getPushZones()
	{
		return $this->sendRequest('pushzone', 'listZones');
	}

	/**
	 * Vod zone methods
	 * @subpackage vodzone
	 */

	/**
	 * Vodzone Create 
	 * Creates a new Vod Zone 
	 * vodzone.create
	 * 
	 * <code>
	 *	$this->createVodZone($name, $password, $label);
	 * </code>
	 * 
	 * Required: string $name, string $password
	 *
	 * @param string $name 
	 * @param string $password	
	 * @param string $label (optional)
	 * @return object xmlrpcresp | array $value (if failed, array(int errorCode, string errorMessage))
	 * 
	 */
	public function createVodZone($name, $password, $label = null)
	{
		if (empty($name) || empty($password))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}

		$zone_values = array($name, $password, $label);
		return $this->sendRequest('vodzone', 'create', array($zone_values));
	}

/**
	 * Vodzone Update 
	 * Updates an existing Vod Zone
	 * vodzone.update
	 * 
	 * <code>
	 *	$this->updateVodZone($zone_id, $name, $password, $label);
	 * </code>
	 * 
	 * Required: int $zone_id
	 *
	 * @param int			$zone_id
	 * @param string	$name (optional)
	 * @param string	$password (optional)
	 * @param string	$label (optional)
	 * @return object xmlrpcresp | int $value (0 is success, 1 if failed)
	 * 
	 */
	public function updateVodZone($zone_id, $name = null, $password = null, $label = null)
	{
		if (empty($zone_id))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}

		$zone_values = array($name, $password, $label);

		return $this->sendRequest('vodzone', 'update', array($zone_id, $zone_values));
	}

	/**
	 * Vodzone Delete 
	 * Deletes an existing Vod Zone
	 * vodzone.delete
	 * 
	 * <code>
	 *	$this->deleteVodZone($zone_id);
	 * </code>
	 * 
	 * Required: int $zone_id
	 *
	 * @param int			$zone_id
	 * @return object xmlrpcresp | int $value (0 is success, 1 if failed)
	 * 
	 */
	public function deleteVodZone($zone_id)
	{
		if (empty($zone_id))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}

		return $this->sendRequest('vodzone', 'delete', array($zone_id));
	}

	/**
	 * Vodzone List Zones 
	 * Lists all Vod Zones on your account
	 * Vodzone.listZones
	 * 
	 * <code>
	 *	$this->getVodZones();
	 * </code>
	 * 
	 * @return object xmlrpcresp | array $value
	 * 
	 */
	public function getVodZones()
	{
		return $this->sendRequest('vodzone', 'listZones');
	}

	/**
	 * Live zone methods
	 * @subpackage livezone
	 */

	/**
	 * Livezone Create 
	 * Creates a new Live Zone 
	 * livezone.create
	 * 
	 * <code>
	 *	$this->createLiveZone($name, $password, $label);
	 * </code>
	 * 
	 * Required: string $name, string $password
	 *
	 * @param string $name 
	 * @param string $password	
	 * @param string $label (optional)
	 * @return object xmlrpcresp | array $value (if failed, array(int errorCode, string errorMessage))
	 * 
	 */
	public function createLiveZone($name, $password, $label = null)
	{
		if (empty($name) || empty($password))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}

		$zone_values = array($name, $password, $label);
		return $this->sendRequest('livezone', 'create', array($zone_values));
	}

	/**
	 * Livezone Update 
	 * Updates an existing Live Zone
	 * livezone.update
	 * 
	 * <code>
	 *	$this->updateLiveZone($zone_id, $name, $password, $label);
	 * </code>
	 * 
	 * Required: int $zone_id
	 *
	 * @param int			$zone_id
	 * @param string	$name (optional)
	 * @param string	$password (optional)
	 * @param string	$label (optional)
	 * @return object xmlrpcresp | int $value (0 is success, 1 if failed)
	 * 
	 */
	public function updateLiveZone($zone_id, $name = null, $password = null, $label = null)
	{
		if (empty($zone_id))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}

		$zone_values = array($name, $password, $label);

		return $this->sendRequest('livezone', 'update', array($zone_id, $zone_values));
	}

/**
	 * Livezone Delete 
	 * Deletes an existing Live Zone
	 * livezone.delete
	 * 
	 * <code>
	 *	$this->deleteLiveZone($zone_id);
	 * </code>
	 * 
	 * Required: int $zone_id
	 *
	 * @param int			$zone_id
	 * @return object xmlrpcresp | int $value (0 is success, 1 if failed)
	 * 
	 */
	public function deleteLiveZone($zone_id)
	{
		if (empty($zone_id))
		{
			throw new MissingRequiredParameterException('One or more required parameters are empty');
		}

		return $this->sendRequest('livezone', 'delete', array($zone_id));
	}

	/**
	 * Livezone List Zones 
	 * Lists all Live Zones on your account
	 * Livezone.listZones
	 * 
	 * <code>
	 *	$this->getLiveZones();
	 * </code>
	 * 
	 * @return object xmlrpcresp | array $value
	 * 
	 */
	public function getLiveZones()
	{
		return $this->sendRequest('livezone', 'listZones');
	}
}