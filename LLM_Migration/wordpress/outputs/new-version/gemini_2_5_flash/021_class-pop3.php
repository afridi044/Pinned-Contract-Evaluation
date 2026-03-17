<?php
/**
 * mail_fetch/setup.php
 *
 * Copyright (c) 1999-2011 CDI (cdi@thewebmasters.net) All Rights Reserved
 * Modified by Philippe Mingo 2001-2009 mingo@rotedic.com
 * An RFC 1939 compliant wrapper class for the POP3 protocol.
 *
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * POP3 class
 *
 * @copyright 1999-2011 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage mail_fetch
 */

class POP3 {
    public string $ERROR      = '';       //  Error string.

    public int $TIMEOUT    = 60;       //  Default timeout before giving up on a
                                //  network operation.

    public int $COUNT      = -1;       //  Mailbox msg count

    public int $BUFFER     = 512;      //  Socket buffer for socket fgets() calls.
                                //  Per RFC 1939 the returned line a POP3
                                //  server can send is 512 bytes.

    public mixed $FP         = null;       //  The connection to the server's
                                //  file descriptor (resource|false|null)

    public string $MAILSERVER = '';       // Set this to hard code the server name

    public bool $DEBUG      = false;    // set to true to echo pop3
                                // commands and responses to error_log
                                // this WILL log passwords!

    public string $BANNER     = '';       //  Holds the banner returned by the
                                //  pop server - used for apop()

    public bool $ALLOWAPOP  = false;    //  Allow or disallow apop()
                                //  This must be set to true
                                //  manually

    public function __construct ( string $server = '', ?int $timeout = null ) {
        // $this->BUFFER is already int due to property declaration
        if( $server !== '' ) {
            // Do not allow programs to alter MAILSERVER
            // if it is already specified. They can get around
            // this if they -really- want to, so don't count on it.
            if($this->MAILSERVER === '')
                $this->MAILSERVER = $server;
        }
        if($timeout !== null) {
            $this->TIMEOUT = $timeout;
            if (!ini_get('safe_mode'))
                set_time_limit($timeout);
        }
        // Constructors do not return values
    }

    public function update_timer (): bool {
        if (!ini_get('safe_mode'))
            set_time_limit($this->TIMEOUT);
        return true;
    }

    public function connect (string $server, int $port = 110): bool  {
        //  Opens a socket to the specified server. Unless overridden,
        //  port defaults to 110. Returns true on success, false on fail

        // If MAILSERVER is set, override $server with it's value

        // The original check `!isset($port) || !$port` is redundant with a default parameter value.
        if($this->MAILSERVER !== '')
            $server = $this->MAILSERVER;

        if($server === ''){
            $this->ERROR = "POP3 connect: " . _("No server specified");
            $this->FP = null; // unset($this->FP) is equivalent to setting to null for properties
            return false;
        }

        $fp = @fsockopen($server, $port, $errno, $errstr);

        if($fp === false) { // Strict comparison for fsockopen return
            $this->ERROR = "POP3 connect: " . _("Error ") . "[$errno] [$errstr]";
            $this->FP = null;
            return false;
        }

        socket_set_blocking($fp, true); // -1 is equivalent to true for blocking
        $this->update_timer();
        $reply = fgets($fp, $this->BUFFER);
        if ($reply === false) { // Handle fgets failure
            $this->ERROR = "POP3 connect: " . _("Error reading banner");
            fclose($fp);
            $this->FP = null;
            return false;
        }
        $reply = $this->strip_clf($reply);
        if($this->DEBUG)
            error_log("POP3 SEND [connect: {$server}] GOT [{$reply}]",0);
        if(!$this->is_ok($reply)) {
            $this->ERROR = "POP3 connect: " . _("Error ") . "[$reply]";
            fclose($fp);
            $this->FP = null;
            return false;
        }
        $this->FP = $fp;
        $this->BANNER = $this->parse_banner($reply);
        return true;
    }

    public function user (string $user = ""): bool {
        // Sends the USER command, returns true or false

        if( $user === "" ) {
            $this->ERROR = "POP3 user: " . _("no login ID submitted");
            return false;
        } elseif($this->FP === null) { // Check for null instead of !isset
            $this->ERROR = "POP3 user: " . _("connection not established");
            return false;
        } else {
            $reply = $this->send_cmd("USER {$user}");
            if(!$this->is_ok($reply)) {
                $this->ERROR = "POP3 user: " . _("Error ") . "[$reply]";
                return false;
            } else
                return true;
        }
    }

    public function pass (string $pass = ""): int|false     { // Returns int for count, false on failure
        // Sends the PASS command, returns # of msgs in mailbox,
        // returns false (undef) on Auth failure

        if($pass === "") {
            $this->ERROR = "POP3 pass: " . _("No password submitted");
            return false;
        } elseif($this->FP === null) {
            $this->ERROR = "POP3 pass: " . _("connection not established");
            return false;
        } else {
            $reply = $this->send_cmd("PASS {$pass}");
            if(!$this->is_ok($reply)) {
                $this->ERROR = "POP3 pass: " . _("Authentication failed") . " [{$reply}]";
                $this->quit();
                return false;
            } else {
                //  Auth successful.
                $count = $this->last("count");
                if ($count === false) { // last() can return false
                    return false;
                }
                $this->COUNT = $count;
                return $count;
            }
        }
    }

    public function apop (string $login, string $pass): int|false { // Returns int for count, false on failure
        //  Attempts an APOP login. If this fails, it'll
        //  try a standard login. YOUR SERVER MUST SUPPORT
        //  THE USE OF THE APOP COMMAND!
        //  (apop is optional per rfc1939)

        if($this->FP === null) {
            $this->ERROR = "POP3 apop: " . _("No connection to server");
            return false;
        } elseif(!$this->ALLOWAPOP) {
            $retVal = $this->login($login,$pass);
            return $retVal;
        } elseif($login === "") {
            $this->ERROR = "POP3 apop: " . _("No login ID submitted");
            return false;
        } elseif($pass === "") {
            $this->ERROR = "POP3 apop: " . _("No password submitted");
            return false;
        } else {
            $banner = $this->BANNER;
            if( ($banner === "") ) { // Simplified check for empty string
                $this->ERROR = "POP3 apop: " . _("No server banner") . ' - ' . _("abort");
                $retVal = $this->login($login,$pass);
                return $retVal;
            } else {
                $AuthString = $banner;
                $AuthString .= $pass;
                $APOPString = md5($AuthString);
                $cmd = "APOP {$login} {$APOPString}";
                $reply = $this->send_cmd($cmd);
                if(!$this->is_ok($reply)) {
                    $this->ERROR = "POP3 apop: " . _("apop authentication failed") . ' - ' . _("abort");
                    $retVal = $this->login($login,$pass);
                    return $retVal;
                } else {
                    //  Auth successful.
                    $count = $this->last("count");
                    if ($count === false) { // last() can return false
                        return false;
                    }
                    $this->COUNT = $count;
                    return $count;
                }
            }
        }
    }

    public function login (string $login = "", string $pass = ""): int|false { // Returns int for count, false on failure
        // Sends both user and pass. Returns # of msgs in mailbox or
        // false on failure (or -1, if the error occurs while getting
        // the number of messages.)

        if( $this->FP === null ) {
            $this->ERROR = "POP3 login: " . _("No connection to server");
            return false;
        } else {
            $fp = $this->FP; // This assignment is not used later in this block, only $this->user and $this->pass are called.
            if( !$this->user( $login ) ) {
                //  Preserve the error generated by user()
                return false;
            } else {
                $count = $this->pass($pass);
                if( ($count === false) || ($count === -1) ) { // Check for false explicitly
                    //  Preserve the error generated by last() and pass()
                    return false;
                } else
                    return $count;
            }
        }
    }

    public function top (int $msgNum, string $numLines = "0"): array|false { // Returns array of lines, false on failure
        //  Gets the header and first $numLines of the msg body
        //  returns data in an array with each returned line being
        //  an array element. If $numLines is empty, returns
        //  only the header information, and none of the body.

        if($this->FP === null) {
            $this->ERROR = "POP3 top: " . _("No connection to server");
            return false;
        }
        $this->update_timer();

        $fp = $this->FP;
        $buffer = $this->BUFFER;
        $cmd = "TOP {$msgNum} {$numLines}";
        fwrite($fp, "TOP {$msgNum} {$numLines}\r\n");
        $reply = fgets($fp, $buffer);
        if ($reply === false) { // Handle fgets failure
            $this->ERROR = "POP3 top: " . _("Error reading response");
            return false;
        }
        $reply = $this->strip_clf($reply);
        if($this->DEBUG) {
            error_log("POP3 SEND [{$cmd}] GOT [{$reply}]",0);
        }
        if(!$this->is_ok($reply))
        {
            $this->ERROR = "POP3 top: " . _("Error ") . "[$reply]";
            return false;
        }

        $count = 0;
        $MsgArray = []; // Short array syntax

        $line = fgets($fp,$buffer);
        while ( !preg_match('/^\.\r\n/',$line))
        {
            if ($line === false) { // Handle fgets failure within loop
                $this->ERROR = "POP3 top: " . _("Error reading message body");
                return false;
            }
            $MsgArray[$count] = $line;
            $count++;
            $line = fgets($fp,$buffer);
            if($line === false)    { break; } // Check for false, not just empty
        }

        return $MsgArray;
    }

    public function pop_list (?int $msgNum = null): array|int|false { // Returns array, int (size), or false
        //  If called with an argument, returns that msgs' size in octets
        //  No argument returns an associative array of undeleted
        //  msg numbers and their sizes in octets

        if($this->FP === null)
        {
            $this->ERROR = "POP3 pop_list: " . _("No connection to server");
            return false;
        }
        $fp = $this->FP;
        $Total = $this->COUNT;
        if( ($Total === false) || ($Total === -1) ) // Check for false explicitly
        {
            return false;
        }
        if($Total === 0)
        {
            return ["0","0"]; // Short array syntax
            // return -1;   // mailbox empty
        }

        $this->update_timer();

        if($msgNum !== null) // Check for null instead of empty
        {
            $cmd = "LIST {$msgNum}";
            fwrite($fp,"{$cmd}\r\n");
            $reply = fgets($fp,$this->BUFFER);
            if ($reply === false) { // Handle fgets failure
                $this->ERROR = "POP3 pop_list: " . _("Error reading response");
                return false;
            }
            $reply = $this->strip_clf($reply);
            if($this->DEBUG) {
                error_log("POP3 SEND [{$cmd}] GOT [{$reply}]",0);
            }
            if(!$this->is_ok($reply))
            {
                $this->ERROR = "POP3 pop_list: " . _("Error ") . "[$reply]";
                return false;
            }
            [$junk,$num,$size] = preg_split('/\s+/',$reply); // Array destructuring
            return (int)$size; // Explicit cast to int
        }
        $cmd = "LIST";
        $reply = $this->send_cmd($cmd);
        if(!$this->is_ok($reply))
        {
            // The original code had a redundant strip_clf here, send_cmd already strips.
            $this->ERROR = "POP3 pop_list: " . _("Error ") .  "[$reply]";
            return false;
        }
        $MsgArray = []; // Short array syntax
        $MsgArray[0] = $Total;
        for($msgC=1;$msgC <= $Total; $msgC++)
        {
            if($msgC > $Total) { break; }
            $line = fgets($fp,$this->BUFFER);
            if ($line === false) { // Handle fgets failure
                $this->ERROR = "POP3 pop_list: " . _("Error reading list item");
                return false;
            }
            $line = $this->strip_clf($line);
            if(str_starts_with($line, '.')) // Use str_starts_with for clarity
            {
                $this->ERROR = "POP3 pop_list: " . _("Premature end of list");
                return false;
            }
            [$thisMsg,$msgSize] = preg_split('/\s+/',$line); // Array destructuring
            $thisMsg = (int)$thisMsg; // Explicit cast
            if($thisMsg !== $msgC) // Strict comparison
            {
                $MsgArray[$msgC] = "deleted";
            }
            else
            {
                $MsgArray[$msgC] = (int)$msgSize; // Explicit cast to int
            }
        }
        return $MsgArray;
    }

    public function get (int $msgNum): array|false { // Returns array of lines, false on failure
        //  Retrieve the specified msg number. Returns an array
        //  where each line of the msg is an array element.

        if($this->FP === null)
        {
            $this->ERROR = "POP3 get: " . _("No connection to server");
            return false;
        }

        $this->update_timer();

        $fp = $this->FP;
        $buffer = $this->BUFFER;
        $cmd = "RETR {$msgNum}";
        $reply = $this->send_cmd($cmd);

        if(!$this->is_ok($reply))
        {
            $this->ERROR = "POP3 get: " . _("Error ") . "[$reply]";
            return false;
        }

        $count = 0;
        $MsgArray = []; // Short array syntax

        $line = fgets($fp,$buffer);
        while ( !preg_match('/^\.\r\n/',$line))
        {
            if ($line === false) { // Handle fgets failure
                $this->ERROR = "POP3 get: " . _("Error reading message body");
                return false;
            }
            if ( $line[0] === '.' ) { $line = substr($line,1); } // String access
            $MsgArray[$count] = $line;
            $count++;
            $line = fgets($fp,$buffer);
            if($line === false)    { break; } // Check for false, not just empty
        }
        return $MsgArray;
    }

    public function last (string $type = "count"): int|array|false { // Returns int, array, or false
        //  Returns the highest msg number in the mailbox.
        //  returns -1 on error, 0+ on success, if type != count
        //  results in a popstat() call (2 element array returned)

        $last = -1;
        if($this->FP === null)
        {
            $this->ERROR = "POP3 last: " . _("No connection to server");
            return $last;
        }

        $reply = $this->send_cmd("STAT");
        if(!$this->is_ok($reply))
        {
            $this->ERROR = "POP3 last: " . _("Error ") . "[$reply]";
            return $last;
        }

        $Vars = preg_split('/\s+/',$reply);
        $count = (int)$Vars[1]; // Explicit cast
        $size = (int)$Vars[2]; // Explicit cast
        if($type !== "count") // Strict comparison
        {
            return [$count,$size]; // Short array syntax
        }
        return $count;
    }

    public function reset (): bool {
        //  Resets the status of the remote server. This includes
        //  resetting the status of ALL msgs to not be deleted.
        //  This method automatically closes the connection to the server.

        if($this->FP === null)
        {
            $this->ERROR = "POP3 reset: " . _("No connection to server");
            return false;
        }
        $reply = $this->send_cmd("RSET");
        if(!$this->is_ok($reply))
        {
            //  The POP3 RSET command -never- gives a -ERR
            //  response - if it ever does, something truely
            //  wild is going on.

            $this->ERROR = "POP3 reset: " . _("Error ") . "[$reply]";
            error_log("POP3 reset: ERROR [{$reply}]",0); // String interpolation
        }
        $this->quit();
        return true;
    }

    public function send_cmd (string $cmd = ""): string|false
    {
        //  Sends a user defined command string to the
        //  POP server and returns the results. Useful for
        //  non-compliant or custom POP servers.
        //  Do NOT includ the \r\n as part of your command
        //  string - it will be appended automatically.

        //  The return value is a standard fgets() call, which
        //  will read up to $this->BUFFER bytes of data, until it
        //  encounters a new line, or EOF, whichever happens first.

        //  This method works best if $cmd responds with only
        //  one line of data.

        if($this->FP === null)
        {
            $this->ERROR = "POP3 send_cmd: " . _("No connection to server");
            return false;
        }

        if($cmd === "")
        {
            $this->ERROR = "POP3 send_cmd: " . _("Empty command string");
            return "";
        }

        $fp = $this->FP;
        $buffer = $this->BUFFER;
        $this->update_timer();
        fwrite($fp,"{$cmd}\r\n");
        $reply = fgets($fp,$buffer);
        if ($reply === false) { // Handle fgets failure
            $this->ERROR = "POP3 send_cmd: " . _("Error reading response for command ") . "[$cmd]";
            return false;
        }
        $reply = $this->strip_clf($reply);
        if($this->DEBUG) { error_log("POP3 SEND [{$cmd}] GOT [{$reply}]",0); }
        return $reply;
    }

    public function quit(): bool {
        //  Closes the connection to the POP3 server, deleting
        //  any msgs marked as deleted.

        if($this->FP === null)
        {
            $this->ERROR = "POP3 quit: " . _("connection does not exist");
            return false;
        }
        $fp = $this->FP;
        $cmd = "QUIT";
        fwrite($fp,"{$cmd}\r\n");
        $reply = fgets($fp,$this->BUFFER);
        if ($reply === false) { // Handle fgets failure
            // Even if fgets fails, we should still try to close the connection.
            $this->ERROR = "POP3 quit: " . _("Error reading QUIT response");
        } else {
            $reply = $this->strip_clf($reply);
            if($this->DEBUG) { error_log("POP3 SEND [{$cmd}] GOT [{$reply}]",0); }
        }
        fclose($fp);
        $this->FP = null; // unset($this->FP)
        return true;
    }

    public function popstat (): array|false { // Returns array or false
        //  Returns an array of 2 elements. The number of undeleted
        //  msgs in the mailbox, and the size of the mbox in octets.

        $PopArray = $this->last("array");

        if($PopArray === false) { return false; } // last() can return false

        if( ($PopArray === null) || empty($PopArray) ) // Check for null explicitly, empty() is fine for array
        {
            return false;
        }
        return $PopArray;
    }function uidl (string $msgNum = ""): array|string|false
    {
        //  Returns the UIDL of the msg specified. If called with
        //  no arguments, returns an associative array where each
        //  undeleted msg num is a key, and the msg's uidl is the element
        //  Array element 0 will contain the total number of msgs

        if(!isset($this->FP)) {
            $this->ERROR = "POP3 uidl: " . _("No connection to server");
            return false;
        }

        $fp = $this->FP;
        $buffer = $this->BUFFER;

        if(!empty($msgNum)) {
            $cmd = "UIDL $msgNum";
            $reply = $this->send_cmd($cmd);
            if(!$this->is_ok($reply))
            {
                $this->ERROR = "POP3 uidl: " . _("Error ") . "[$reply]";
                return false;
            }
            [$ok, $num, $myUidl] = preg_split('/\s+/', $reply);
            return $myUidl;
        } else {
            $this->update_timer();

            $UIDLArray = [];
            $Total = $this->COUNT;
            $UIDLArray[0] = $Total;

            if ($Total < 1)
            {
                return $UIDLArray;
            }
            $cmd = "UIDL";
            fwrite($fp, "UIDL\r\n");
            $reply = fgets($fp, $buffer);
            $reply = $this->strip_clf($reply);
            if($this->DEBUG) { @error_log("POP3 SEND [$cmd] GOT [$reply]",0); }
            if(!$this->is_ok($reply))
            {
                $this->ERROR = "POP3 uidl: " . _("Error ") . "[$reply]";
                return false;
            }

            $line = "";
            $count = 1;
            $line = fgets($fp,$buffer);
            while ( !preg_match('/^\.\r\n/',$line)) {
                [$msg, $msgUidl] = preg_split('/\s+/', $line);
                $msgUidl = $this->strip_clf($msgUidl);
                if($count === (int)$msg) { // Cast $msg to int for strict comparison
                    $UIDLArray[$msg] = $msgUidl;
                }
                else
                {
                    $UIDLArray[$count] = 'deleted';
                }
                $count++;
                $line = fgets($fp,$buffer);
            }
        }
        return $UIDLArray;
    }

    function delete (string $msgNum = ""): bool {
        //  Flags a specified msg as deleted. The msg will not
        //  be deleted until a quit() method is called.

        if(!isset($this->FP))
        {
            $this->ERROR = "POP3 delete: " . _("No connection to server");
            return false;
        }
        if(empty($msgNum))
        {
            $this->ERROR = "POP3 delete: " . _("No msg number submitted");
            return false;
        }
        $reply = $this->send_cmd("DELE $msgNum");
        if(!$this->is_ok($reply))
        {
            $this->ERROR = "POP3 delete: " . _("Command failed ") . "[$reply]";
            return false;
        }
        return true;
    }

    //  *********************************************************

    //  The following methods are internal to the class.

    function is_ok (string $cmd = ""): bool {
        //  Return true or false on +OK or -ERR

        return empty($cmd) ? false : (stripos($cmd, '+OK') !== false);
    }

    function strip_clf (string $text = ""): string {
        // Strips \r\n from server responses

        return empty($text) ? $text : str_replace(["\r","\n"],'',$text);
    }

    function parse_banner (string $server_text): string {
        $outside = true;
        $banner = "";
        $length = strlen($server_text);
        for($count =0; $count < $length; $count++)
        {
            $digit = substr($server_text,$count,1);
            // Removed redundant if(!empty($digit)) as substr(...,1) always returns a non-empty string within loop bounds
            if( (!$outside) && ($digit != '<') && ($digit != '>') )
            {
                $banner .= $digit;
            }
            if ($digit == '<')
            {
                $outside = false;
            }
            if($digit == '>')
            {
                $outside = true;
            }
        }
        $banner = $this->strip_clf($banner);    // Just in case
        return "<$banner>";
    }

}   // End class

// Removed PHP 4 compatibility block for stripos as it is standard since PHP 5.0.0
// and thus unnecessary for PHP 8.3 standards.
// if (!function_exists("stripos")) {
//     function stripos($haystack, $needle){
//         return strpos($haystack, stristr( $haystack, $needle ));
//     }
// }