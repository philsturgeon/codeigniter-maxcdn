# CodeIgniter-MaxCDN

Work with the MaxCDN API to create, edit or delete pretty much anything.

## Requirements

1. PHP 5.3.x
2. CodeIgniter 2.1.0

## Examples

	$this->load->spark('maxcdn');
	$result = $this->maxcdn->purgeFromCache('http://cdn.thenextmen.com/assets/js/jquery.js');
	
	var_dump($result); // "1"