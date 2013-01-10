<?php
/**
 * Swift Mailer plugin for PHPixie. 
 *
 * This module is not included by default, download it here:
 *
 * https://github.com/dracony/PHPixie-swift-mailer
 * 
 * To enable it add 'email' to modules array in /application/config/core.php,
 * then download Swift Mailer from http://swiftmailer.org/ and put contents
 * of its /lib folder inside /modules/email/vendor/swift folder.
 * 
 * @link https://github.com/dracony/PHPixie-swift-mailer Download this module from Github
 * @package    Email
 */
class Email {

	/**
	 * Initialized Swift_Mailer instance
	 * @var    Swift_Mailer
	 * @access public
	 * @see    http://swiftmailer.org/docs/sending.html
	 */
	public $mailer;
	
	/**
	 * An array of created Email instances, one for each driver
	 * @var    array
	 * @access protected
	 */
	protected static $pool;

	/**
	 * Creates an Email instance for specified driver.
	 *
	 * @param   string  $config  Configuration name of the connection. Defaults to 'default'.
	 * @return  Email   Initialized Email object
	 */
	protected function __construct($config = 'default')	{
	
		//Load SwiftMailer classes
		if ( !class_exists('Swift_Mailer', false))
			include Misc::find_file('vendor', 'swift/swift_required');
		
		$type = Config::get("email.{$config}.type",'native');
		switch ($type) {
			case 'smtp':
				
				// Create SMTP Transport
				$transport = Swift_SmtpTransport::newInstance(
					Config::get("email.{$config}.hostname"),
					Config::get("email.{$config}.port",25)
				);
				
				// Set encryption if specified
				if ( ($encryption = Config::get("email.{$config}.encryption",false)) !== false)
					$transport->setEncryption($encryption);
				
				// Set username if specified
				if ( ($username = Config::get("email.{$config}.username",false)) !== false)
					$transport->setUsername($username);
					
				// Set password if specified
				if ( ($password = Config::get("email.{$config}.password",false)) !== false)
					$transport->setPassword($password);
					
				// Set timeout, defaults to 5 seconds
				$transport->setTimeout(Config::get("email.{$config}.timeout", 5));
				
			break;
			
			case 'sendmail':
				
				// Create a sendmail connection, defalts to "/usr/sbin/sendmail -bs"
				$transport = Swift_SendmailTransport::newInstance(Config::get("email.{$config}.sendmail_command", "/usr/sbin/sendmail -bs"));
				
			break;
			
			case 'native':
				
				// Use the native connection and specify additional params, defaults to "-f%s"
				$transport = Swift_MailTransport::newInstance(Config::get("email.{$config}.mail_parameters","-f%s"));
				
			break;
			
			default:
				throw new Exception("Connection can be one of the following: smtp, sendmail or native. You specified '{$type}' as type");
		}

		$this->mailer = Swift_Mailer::newInstance($transport);
	}

	/**
	 * Sends an email message.
	 * <code>
	 * //$to and $from parameters can be one of these
	 * 'user@server.com'
	 * array('user@server.com' => 'User Name')
	 *
	 * //$to accepts multiple recepients
	 * array(
	 *     'user@server.com',
	 *     array('user2@server.com' => 'User Name')
	 * )
	 *
	 * //You can specify To, Cc and Bcc like this
	 * array(
	 *     'to' => array(
	 *         'user@server.com',
	 *         array('user2@server.com' => 'User Name')
	 *      ),
	 *      'cc' => array(
	 *         'user3@server.com',
	 *         array('user4@server.com' => 'User Name')
	 *      ),
	 *      'bcc' => array(
	 *         'user5@server.com',
	 *         array('user6@server.com' => 'User Name')
	 *      )
	 * );
 	 * </code>
	 *
	 * @param   string|array $to        Recipient email (and name), or an array of To, Cc, Bcc names
	 * @param   string|array $from      Sender email (and name)
	 * @param   string       $subject   Message subject
	 * @param   string       $message   Message body
	 * @param   boolean      $html      Send email as HTML
	 * @param   string 		 $config    Configuration name of the connection. Defaults to 'default'.
	 * @return  integer      Number of emails sent
	 */
	public function send_email($to, $from, $subject, $message, $html = false) {
		
		// Create the message
		$message = Swift_Message::newInstance($subject, $message, $html?'text/html':'text/plain', 'utf-8');
		
		//Normalize the input array
		if (is_string($to)) {
		
			//No name specified
			$to = array('to' => array($to));
			
		} elseif(is_array($to) && is_string(key($to)) && is_string(current($to))) {
		
			//Single recepient with name
		    $to = array('to' => array($to));
			
		} elseif(is_array($to) && is_numeric(key($to))) {
		
			//Multiple recepients
			$to = array('to' => $to);
			
		}
		
		foreach ($to as $type => $set) {
			$type=strtolower($type);
			if (!in_array($type, array('to', 'cc', 'bcc'), true))
				throw new Exception("You can only specify 'To', 'Cc' or 'Bcc' recepients. You attempted to specify {$type}.");
				
			// Get method name
			$method = 'add'.ucfirst($type);
			foreach($set as $recepient) {
				Debug::log($recepient);
				if(is_array($recepient))
					$message->$method(key($recepient),current($recepient));
				else
					$message->$method($recepient);
			}
		}

		if(is_array($from))
			$message->setFrom(key($from),current($from));
		else
			$message->setFrom($from);

		return $this->mailer->send($message);
	}
	
	/**
	 * Gets an Email instance for the specified driver.
	 *
	 * @param   string $config Configuration name of the connection. Defaults to 'default'.
	 * @return  Email  Initialized Email object
	 */
	public static function instance($config) {
	
		//Create instance of the connection if it wasn't created yet
		if (!isset(Email::$pool[$config]))
			Email::$pool[$config]=new Email($config);
			
		return Email::$pool[$config];
	}
	
	/**
	 * Shortcut function for sending an email message.
	 *
	 * @param   string|array $to        Recipient email (and name), or an array of To, Cc, Bcc names
	 * @param   string|array $from      Sender email (and name)
	 * @param   string       $subject   Message subject
	 * @param   string       $message   Message body
	 * @param   boolean      $html      Send email as HTML
	 * @param   string 		 $config    Configuration name of the connection. Defaults to 'default'.
	 * @return  integer      Number of emails sent
	 * @see Email::send_email()
	 */
	public static function send($to, $from, $subject, $message, $html = false, $config = 'default') {
		return Email::instance($config)->send_email($to,$from,$subject,$message,$html);
	}

}
?>