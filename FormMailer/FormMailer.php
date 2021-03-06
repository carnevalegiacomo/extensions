<?php
/**
 * FormMailer extension - Formats and sends posted form fields to email recipients
 *
 * See http://www.mediawiki.org/wiki/Extension:FormMailer for installation and usage details
 * Started: 2007-06-17
 *
 * @package MediaWiki
 * @subpackage Extensions
 * @author [http://www.organicdesign.co.nz/User:Nad User:Nad]
 * @copyright © 2007 [http://www.organicdesign.co.nz/User:Nad User:Nad]
 * @licence GNU General Public Licence 2.0 or later
 */
if( !defined( 'MEDIAWIKI' ) ) die( 'Not an entry point.' );
define( 'FORMMAILER_VERSION', '1.0.6, 2013-07-05' );

// A list of email addresses which should recieve posted forms
$wgFormMailerRecipients = array();

// If a variable of this name is posted, the data is assumed to be for mailing
$wgFormMailerVarName    = "formmailer";

// Name of sender of forms
$wgFormMailerFrom       = 'wiki@' . preg_replace( '|^.+www\.|', '', $wgServer );

// Don't post the following posted items
$wgFormMailerDontSend   = array( 'title', 'action' );

// Message to display after sending the form (can also be set in the form by posting formmailer_message
$wgFormMailerMessage    = "Thanks, your enquiry has been submitted!";

// Message to display after sending the form (can also be set in the form by posting formmailer_subject
$wgFormMailerSubject    = "Form submitted from $wgSitename";

// Add a JavaScript test to protect against spambot posts
$wgFormMailerAntiSpam   = true;

$wgExtensionFunctions[] = 'wfSetupFormMailer';

$wgExtensionCredits['other'][] = array(
	'name'        => 'FormMailer',
	'author'      => '[http://www.organicdesign.co.nz/nad User:Nad]',
	'description' => 'Formats and sends posted form fields to email recipients',
	'url'         => 'http://www.mediawiki.org/wiki/Extension:FormMailer',
	'version'     => FORMMAILER_VERSION
);

function wfSetupFormMailer() {
	global $wgFormMailerVarName, $wgFormMailerRecipients, $wgFormMailerMessage, $wgFormMailerSubject,
		$wgFormMailerFrom, $wgFormMailerDontSend, $wgResourceModules,
		$wgRequest, $wgSiteNotice, $wgSitename, $wgFormMailerAntiSpam, $wgOut, $wgJsMimeType;

	$ip = $_SERVER['REMOTE_ADDR'];
	$ap = $wgFormMailerAntiSpam ? '-' . md5( $ip ) : '';
	$from_email = '';

	if( $wgRequest->getText( $wgFormMailerVarName . $ap ) ) {

		// Construct the message
		$body    = "Form posted from $ip\n\n";
		$message = $wgFormMailerMessage;
		$subject = $wgFormMailerSubject;
		foreach( $wgRequest->getValues() as $k => $v ) {
			if( !in_array( $k, $wgFormMailerDontSend ) ) {
				$k = str_replace( '_', ' ', $k );
				if     ( $k == 'formmailer message' ) $message = $v;
				elseif ( $k == 'formmailer subject' ) $subject = $v;
				elseif ( $k != $wgFormMailerVarName ) $body .= "$k: $v\n\n";
				if( preg_match( "|^email|i", $k ) ) $from_email = $v;
			}
		}

		// Send to recipients using the MediaWiki mailer
		$err  = '';
		$user = new User();
		$site = "\"$wgSitename\"<$wgFormMailerFrom>";
		foreach( $wgFormMailerRecipients as $recipient ) {
			if( User::isValidEmailAddr( $recipient ) ) {
				$from = new MailAddress( $from_email );
				$to = new MailAddress( $recipient );
				$status = UserMailer::send( $to, $from, $subject, $body );
				if( !is_object( $status ) || !$status->ok ) $err = 'Failed to send!';
			}
		}
		$wgSiteNotice .= "<div class='usermessage'>" . ( $err ? $err : $message ) . "</div>";
	}
	
	// Add the antispam script
	// - adds the MD5 of the IP address to the formmailer input name after page load
	if( $wgFormMailerAntiSpam ) {
		$wgResourceModules['ext.formmailer'] = array(
			'scripts'       => array( 'formmailer.js' ),
			'localBasePath' => dirname( __FILE__ ),
			'remoteExtPath' => basename( dirname( __FILE__ ) ),
		);
		$wgOut->addModules( 'ext.formmailer' );
		$wgOut->addJsConfigVars( 'wgFormMailerAP', $ap );
	}

}

