<?php
namespace RubenArakelyan\NoTo0870;

/**
 * Class NoTo0870
 * @package RubenArakelyan\NoTo0870
 */
class NoTo0870
{

    private $ch;
    private $url = 'http://www.saynoto0870.com/numbersearch.php';
    private $phone_numbers = [];
    private $debug = false;

    /**
     * Constructor
     */
    public function __construct($debug = false)
    {
        // Set debugging mode
        $this->debug = $debug;
        
        // Create a new instance of cURL
        $this->ch = curl_init();
        
        // Set the URL
        curl_setopt($this->ch, CURLOPT_URL, $this->url);
        
        // Set the user agent
        // It does not provide SayNoTo0870.com with any personal information
        // but helps them track usage of this PHP class.
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'NoTo0870 PHP API for SayNoTo0870.com (+https://github.com/rubenarakelyan/NoTo0870)');
        
        // POST rather than GET
        curl_setopt($this->ch, CURLOPT_POST, 1);
        
        // Return the result of the query
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        // Delete the instance of cURL
        curl_close($this->ch);
    }

    /**
	 * Send a query
	 *
	 * @return JSON
	 */
    public function query($phone_number)
    {
        // Exit if the number is not defined
        if (!isset($phone_number) || $phone_number == '') {
            return $this->_error('Phone number to convert not provided.');
        }
        
        // Query the site
        $result = $this->_execute_query($phone_number);
        
        // Set up a DOM document and suppress errors caused by malformed HTML
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        
        // Load the resulting HTML into the DOM document
        $doc->loadHTML($result);
        
        // DEBUG: Output any errors
        $this->_debug(libxml_get_errors(), 'LibXML errors');
        
        // Set up XPath and extract what we need from the HTML
        $xpath = new DOMXPath($doc);
        $query = '//div[@class="boardcontainer"]/table/tr/td[contains(@class,"windowbg2")]';
        $tds = $xpath->query($query);
        
        // DEBUG: Output the results of the XPath query
        $this->_debug($tds, 'XPath query results');
        
        // Go through all the extracted data and find the phone numbers
        foreach ($tds as $td) {
            // Trim the node value
            $value = trim(utf8_decode($td->nodeValue));
            $value = trim($value, chr(0xC2).chr(0xA0));
            
            // DEBUG: Output the node value
            $this->_debug($value, 'XPath query node value');
            
            // Match phone numbers starting with 01/02/03/0500/080 (local or freephone numbers)
            $matched = preg_match('/^(01|02|03|0500|080)[0-9\s]+$/', $value, $extract);
            if ($matched === 1) {
                // Found a phone number - add it to the array
                $this->phone_numbers[] = $extract[0];
            }
        }
        
        // Return all the phone numbers
        if (empty($this->phone_numbers)) {
            return $this->_error('No phone numbers found.');
        } else {
            return json_encode(['phone_numbers' => $this->phone_numbers]);
        }
    }

    /**
	 * Execute a query
	 *
	 * @return JSON
	 */
    private function _execute_query($phone_number)
    {
        // Assemble the data to send
        $fields = 'number=' . $phone_number;
        
        // Set the fields to send
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $fields);
        
        // Get the result
        $result = curl_exec($this->ch);
        
        // Find out if all is OK
        if (!$result) {
            // A problem occurred with cURL
            return $this->_error('cURL error occurred: ' . curl_error($this->ch));
        } else {
            $http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
            if ($http_code == 404) {
                // Received a 404 error querying the site
                return $this->_error('Could not reach the server.');
            }
            
            return $result;
        }
    }

    /**
	 * Return an error message
	 *
	 * @return JSON
	 */
    private function _error($error_message)
    {
        return json_encode(['error_message' => $error_message]);
    }

    /**
	 * Print out debugging messages
	 *
	 * @return void
	 */
    private function _debug($debug_message, $debug_message_title = '')
    {
        if ($this->debug) {
            echo '<pre><strong>' . $debug_message_title . '</strong><br>' . print_r($debug_message, true) . '</pre>';
        }
    }
}

?>