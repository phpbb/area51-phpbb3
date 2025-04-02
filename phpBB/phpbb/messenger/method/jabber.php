<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

namespace phpbb\messenger\method;

/**
 *
 * Based on Jabber class from Flyspray project
 *
 * @version class.jabber2.php 1595 2008-09-19 (0.9.9)
 * @copyright 2006 Flyspray.org
 * @author Florian Schmitz (floele)
 *
 * Slightly modified by Acyd Burn (2006)
 * Refactored to a service (2024)
 */
class jabber extends base
{
	/** @var string */
	protected $connect_server;

	/** @var resource|null */
	protected $connection = null;

	/** @var bool */
	protected $enable_logging = true;

	/** @var array */
	protected $features = [];

	/** @var array */
	protected $jid = [];

	/** @var array */
	protected $log_array = [];

	/** @var string */
	protected $password;

	/** @var int */
	protected $port;

	/** @var string */
	protected $resource = 'functions_jabber.phpbb.php';

	/** @var string */
	protected $server;

	/** @var array */
	protected $session = [];

	/** @var array */
	protected $stream_options = [];

	/** @var int */
	protected $timeout = 10;

	/** @var array */
	protected $to = [];

	/** @var bool */
	protected $use_ssl = false;

	/** @var string */
	protected $username;

	/** @var string Stream close handshake */
	private const STREAM_CLOSE_HANDSHAKE = '</stream:stream>';

	/**
	 * Set initial parameter values
	 * To init correctly, username() call should go before server()
	 * and ssl() call should go before port() and stream_options() calls.
	 *
	 * Example:
	 * $this->username($username)
	 *		->password($password)
	 *		->ssl($use_ssl)
	 *		->server($server)
	 *		->port($port)
	 *		->stream_options(
	 *			'verify_peer' => true,
	 *			'verify_peer_name' => true,
	 *			'allow_self_signed' => false,
	 *		);
	 *
	 * @return void
	 */
	public function init(): void
	{
		$this->username($this->config['jab_username'])
			->password($this->config['jab_password'])
			->ssl((bool) $this->config['jab_use_ssl'])
			->server($this->config['jab_host'])
			->port($this->config['jab_port'])
			->stream_options['ssl'] = [
				'verify_peer' => $this->config['jab_verify_peer'],
				'verify_peer_name' => $this->config['jab_verify_peer_name'],
				'allow_self_signed' => $this->config['jab_allow_self_signed'],
			];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): int
	{
		return self::NOTIFY_IM;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_queue_object_name(): string
	{
		return 'jabber';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_enabled(): bool
	{
		return
			!empty($this->config['jab_enable']) &&
			!empty($this->config['jab_host']) &&
			!empty($this->config['jab_username']) &&
			!empty($this->config['jab_password']);
	}

	/**
	 * Set ssl context options
	 * See http://php.net/manual/en/context.ssl.php
	 *
	 * @param array $options SSL context options array
	 * @return self
	 */
	public function stream_options(array $options = []): self
	{
		if ($this->use_ssl)
		{
			// Change default stream options if needed
			$this->stream_options['ssl'] = array_merge($this->stream_options['ssl'], $options);
		}

		return $this;
	}

	/**
	 * Set password to connect to server
	 *
	 * @param string $password Password to connect to server
	 * @return self
	 */
	public function password(string $password = ''): self
	{
		$this->password	= html_entity_decode($password, ENT_COMPAT);

		return $this;
	}

	/**
	 * Set use of ssl to connect to server
	 *
	 * @param bool $use_ssl Flag indicating use of ssl to connect to server
	 * @return self
	 */
	public function ssl(bool $use_ssl = false): self
	{
		$this->use_ssl = $use_ssl && self::can_use_ssl();

		return $this;
	}

	/**
	 * Set port to connect to server
	 * use_ssl flag should be set first
	 *
	 * @param int $port Port to connect to server
	 * @return self
	 */
	public function port(int $port = 5222): self
	{
		$this->port	= ($port > 0) ? $port : 5222;

		// Change port if we use SSL
		if ($this->port == 5222 && $this->use_ssl)
		{
			$this->port = 5223;
		}

		return $this;
	}

	/**
	 * Set username to connect to server
	 *
	 * @param string $username Username to connect to server
	 * @return self
	 */
	public function username(string $username = ''): self
	{
		if (str_contains($username, '@'))
		{
			$this->jid = explode('@', $username, 2);
			$this->username = $this->jid[0];
		}
		else
		{
			$this->username = $username;
		}

		return $this;
	}

	/**
	 * Set server to connect
	 * Username should be set first
	 *
	 * @param string $server Server to connect
	 * @return self
	 */
	public function server(string $server = ''): self
	{
		$this->connect_server = $server ?: 'localhost';
		$this->server = $this->jid[1] ?? $this->connect_server;

		return $this;
	}

	/**
	 * Check if it's possible to use the SSL functionality
	 *
	 * @return bool
	 */
	public static function can_use_ssl(): bool
	{
		return @extension_loaded('openssl');
	}

	/**
	 * Check if it's possible to use TLS functionality
	 *
	 * @return bool
	 */
	public static function can_use_tls(): bool
	{
		if (!@extension_loaded('openssl') || !function_exists('stream_socket_enable_crypto') || !function_exists('stream_get_meta_data') || !function_exists('stream_set_blocking') || !function_exists('stream_get_wrappers'))
		{
			return false;
		}

		/**
		* Make sure the encryption stream is supported
		* Also seem to work without the crypto stream if correctly compiled

		$streams = stream_get_wrappers();

		if (!in_array('streams.crypto', $streams))
		{
			return false;
		}
		*/

		return true;
	}

	/**
	 * Sets the resource which is used. No validation is done here, only escaping
	 *
	 * @param string $name
	 * @return void
	 */
	public function set_resource(string $name): void
	{
		$this->resource = $name;
	}

	/**
	 * Connect to the server
	 *
	 * @return bool
	 */
	public function connect(): bool
	{
/*		if (!$this->check_jid($this->username . '@' . $this->server))
		{
			$this->add_to_log('Error: Jabber ID is not valid: ' . $this->username . '@' . $this->server);
			return false;
		}*/

		$this->session['ssl'] = $this->use_ssl;

		if ($this->open_socket($this->connect_server, $this->port))
		{
			$this->send_xml("<?xml version='1.0' encoding='UTF-8' ?" . ">\n");
			$this->send_xml("<stream:stream to='{$this->server}' xmlns='jabber:client' xmlns:stream='http://etherx.jabber.org/streams' version='1.0'>\n");
		}
		else
		{
			$this->add_to_log('Error: connect() #2');
			return false;
		}

		// Now we listen what the server has to say...and give appropriate responses
		$this->response($this->listen());
		return true;
	}

	/**
	 * Disconnect from the server
	 *
	 * @return bool
	 */
	public function disconnect(): bool
	{
		if ($this->connected())
		{
			// disconnect gracefully
			if (isset($this->session['sent_presence']))
			{
				$this->send_presence('offline', '', true);
			}

			$this->send(self::STREAM_CLOSE_HANDSHAKE);
			// Check stream close handshake reply
			$stream_close_reply = $this->listen();

			if ($stream_close_reply != self::STREAM_CLOSE_HANDSHAKE)
			{
				$this->add_to_log("Error: Unexpected stream close handshake reply ”{$stream_close_reply}”");
			}

			$this->session = [];
			/** @psalm-suppress InvalidPropertyAssignmentValue */
			return fclose($this->connection);
		}

		return false;
	}

	/**
	 * Check if it's still connected to the server
	 *
	 * @return bool
	 */
	public function connected(): bool
	{
		return is_resource($this->connection) && !feof($this->connection);
	}

	/**
	 * Initiates login (using data from contructor, after calling connect())
	 *
	 * @return bool
	 */
	public function login(): bool
	{
		if (empty($this->features))
		{
			$this->add_to_log('Error: No feature information from server available.');
			return false;
		}

		return $this->response($this->features);
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_addresses(array $user_row): void
	{
		if (isset($user_row['user_jabber']) && $user_row['user_jabber'])
		{
			$this->to($user_row['user_jabber'], (isset($user_row['username']) ? $user_row['username'] : ''));
		}
	}

	/**
	 * Sets jabber contact to send message to
	 *
	 * @param string	$address	Jabber "To" recipient address
	 * @param string	$realname	Jabber "To" recipient name
	 * @return void
	 */
	public function to(string $address, string $realname = ''): void
	{
		// IM-Addresses could be empty
		if (!trim($address))
		{
			return;
		}

		$pos = !empty($this->to) ? count($this->to) : 0;
		$this->to[$pos]['uid'] = trim($address);
		$this->to[$pos]['name'] = trim($realname);
	}

	/**
	 * Resets all the data (address, template file, etc) to default
	 */
	public function reset(): void
	{
		$this->subject = $this->msg = '';
		$this->additional_headers = $this->to = [];
		$this->use_queue = true;
		unset($this->template);
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_use_queue(bool $use_queue = true): void
	{
		$this->use_queue = !$this->config['jab_package_size'] ? false : $use_queue;
	}

	/**
	 * {@inheritDoc}
	 */
	public function process_queue(array &$queue_data): void
	{
		$queue_object_name = $this->get_queue_object_name();
		$messages_count = count($queue_data[$queue_object_name]['data']);

		if (!$this->is_enabled() || !$messages_count)
		{
			unset($queue_data[$queue_object_name]);
			return;
		}

		@set_time_limit(0);

		$package_size = $queue_data[$queue_object_name]['package_size'] ?? 0;
		$num_items = (!$package_size || $messages_count < $package_size) ? $messages_count : $package_size;

		for ($i = 0; $i < $num_items; $i++)
		{
			// Make variables available...
			extract(array_shift($queue_data[$queue_object_name]['data']));

			if (!$this->connect())
			{
				$this->error($this->user->lang['ERR_JAB_CONNECT'] . '<br />' . $this->get_log());
				return;
			}

			if (!$this->login())
			{
				$this->error($this->user->lang['ERR_JAB_AUTH'] . '<br />' . $this->get_log());
				return;
			}

			foreach ($addresses as $address)
			{
				if ($this->send_message($address, $msg, $subject) === false)
				{
					$this->error($this->get_log());
					continue;
				}
			}
		}

		// No more data for this object? Unset it
		if (!count($queue_data[$queue_object_name]['data']))
		{
			unset($queue_data[$queue_object_name]);
		}

		$this->disconnect();
	}

	/**
	 * {@inheritDoc}
	 */
	public function send(): bool
	{
		$this->prepare_message();

		if (empty($this->to))
		{
			$this->add_to_log('Error: Could not send, recepient addresses undefined.');
			return false;
		}

		$addresses = [];
		foreach ($this->to as $uid_ary)
		{
			$addresses[] = $uid_ary['uid'];
		}
		$addresses = array_unique($addresses);

		if (!$this->use_queue)
		{
			if (!$this->connect())
			{
				$this->error($this->user->lang['ERR_JAB_CONNECT'] . '<br />' . $this->get_log());
				return false;
			}

			if (!$this->login())
			{
				$this->error($this->user->lang['ERR_JAB_AUTH'] . '<br />' . $this->get_log());
				return false;
			}

			foreach ($addresses as $address)
			{
				if ($this->send_message($address, $this->msg, $this->subject) === false)
				{
					$this->error($this->get_log());
					continue;
				}
			}

			$this->disconnect();
		}
		else
		{
			$this->queue->init('jabber', $this->config['jab_package_size']);
			$this->queue->put('jabber', [
				'addresses'		=> $addresses,
				'subject'		=> $this->subject,
				'msg'			=> $this->msg,
			]);
		}
		unset($addresses);

		$this->reset();

		return true;
	}

	/**
	 * Send data to the Jabber server
	 *
	 * @param string $xml
	 * @return int|bool
	 */
	public function send_xml(string $xml): int|bool
	{
		if ($this->connected())
		{
			$xml = trim($xml);
			return fwrite($this->connection, $xml);
		}
		else
		{
			$this->add_to_log('Error: Could not send, connection lost (flood?).');
			return false;
		}
	}

	/**
	 * Open socket
	 *
	 * @param string $server Host to connect to
	 * @param int $port Port number
	 *
	 * @return bool
	 */
	public function open_socket(string $server, int $port): bool
	{
		if (@function_exists('dns_get_record'))
		{
			$record = @dns_get_record("_xmpp-client._tcp.$server", DNS_SRV);
			if (!empty($record) && !empty($record[0]['target']))
			{
				$server = $record[0]['target'];
			}
		}

		$remote_socket = $this->use_ssl ? 'ssl://' . $server . ':' . $port : $server . ':' . $port;
		$socket_context = stream_context_create($this->stream_options);

		if ($this->connection = @stream_socket_client($remote_socket, $errorno, $errorstr, $this->timeout, STREAM_CLIENT_CONNECT, $socket_context))
		{
			stream_set_blocking($this->connection, 0);
			stream_set_timeout($this->connection, 60);

			return true;
		}

		// Apparently an error occurred...
		$this->add_to_log('Error: open_socket() - ' . $errorstr);
		return false;
	}

	/**
	 * Get connection log
	 *
	 * @return string
	 */
	public function get_log(): string
	{
		if ($this->enable_logging && count($this->log_array))
		{
			return implode("<br /><br />", $this->log_array);
		}

		return '';
	}

	/**
	 * Add information to log
	 *
	 * @param string $string Log entry
	 * @return void
	 */
	protected function add_to_log(string $string): void
	{
		if ($this->enable_logging)
		{
			$this->log_array[] = utf8_htmlspecialchars($string);
		}
	}

	/**
	 * Listens to the connection until it gets data or the timeout is reached.
	 * Thus, it should only be called if data is expected to be received.
	 *
	 * @param int $timeout Connection timeout
	 * @param bool $wait Flag indicating if it should wait for the responce until timeout
	 * @return bool|array Either false for timeout or an array with the received data
	 */
	public function listen(int $timeout = 10, bool $wait = false): bool|array
	{
		if (!$this->connected())
		{
			return false;
		}

		// Wait for a response until timeout is reached
		$start = time();
		$data = '';

		do
		{
			$read = trim(fread($this->connection, 4096));
			$data .= $read;
		}
		while (time() <= $start + $timeout && !feof($this->connection) && ($wait || $data == '' || $read != '' || (substr(rtrim($data), -1) != '>')));

		if ($data != '')
		{
			return $this->xmlize($data);
		}
		else
		{
			$this->add_to_log('Timeout, no response from server.');
			return false;
		}
	}

	/**
	 * Initiates account registration (based on data used for constructor)
	 *
	 * @return bool|null
	 */
	public function register(): bool|null
	{
		if (!isset($this->session['id']) || isset($this->session['jid']))
		{
			$this->add_to_log('Error: Cannot initiate registration.');
			return false;
		}

		$this->send_xml("<iq type='get' id='reg_1'><query xmlns='jabber:iq:register'/></iq>");
		return $this->response($this->listen());
	}

	/**
	 * Sets account presence. No additional info required (default is "online" status)
	 *
	 * @param string	$message		Account status (online, offline)
	 * @param string	$type			Status type (dnd, away, chat, xa or nothing)
	 * @param bool		$unavailable	Set to true to make unavailable status
	 * @return int|bool
	 */
	function send_presence(string $message = '', string $type = '', bool $unavailable = false): int|bool
	{
		if (!isset($this->session['jid']))
		{
			$this->add_to_log('ERROR: send_presence() - Cannot set presence at this point, no jid given.');
			return false;
		}

		$type = strtolower($type);
		$type = (in_array($type, array('dnd', 'away', 'chat', 'xa'))) ? '<show>'. $type .'</show>' : '';

		$unavailable = ($unavailable) ? " type='unavailable'" : '';
		$message = ($message) ? '<status>' . utf8_htmlspecialchars($message) .'</status>' : '';

		$this->session['sent_presence'] = !$unavailable;

		return $this->send_xml("<presence$unavailable>" . $type . $message . '</presence>');
	}

	/**
	 * This handles all the different XML elements
	 *
	 * @param array $xml
	 * @return bool
	 */
	function response(array $xml): bool
	{
		if (!count($xml))
		{
			return false;
		}

		// did we get multiple elements? do one after another
		// array('message' => ..., 'presence' => ...)
		if (count($xml) > 1)
		{
			foreach ($xml as $key => $value)
			{
				$this->response(array($key => $value));
			}
			return true;
		}
		else if (is_array(reset($xml)) && count(reset($xml)) > 1)
		{
			// or even multiple elements of the same type?
			// array('message' => array(0 => ..., 1 => ...))
			foreach (reset($xml) as $value)
			{
				$this->response(array(key($xml) => array(0 => $value)));
			}
			return true;
		}

		switch (key($xml))
		{
			case 'stream:stream':
				// Connection initialised (or after authentication). Not much to do here...

				if (isset($xml['stream:stream'][0]['#']['stream:features']))
				{
					// we already got all info we need
					$this->features = $xml['stream:stream'][0]['#'];
				}
				else
				{
					$this->features = $this->listen();
				}

				$second_time = isset($this->session['id']);
				$this->session['id'] = isset($xml['stream:stream'][0]['@']['id']) ? $xml['stream:stream'][0]['@']['id'] : '';

				if ($second_time)
				{
					// If we are here for the second time after TLS, we need to continue logging in
					return $this->login();
				}

				// go on with authentication?
				if (isset($this->features['stream:features'][0]['#']['bind']) || !empty($this->session['tls']))
				{
					return $this->response($this->features);
				}
				return false;
			break;

			case 'stream:features':
				// Resource binding after successful authentication
				if (isset($this->session['authenticated']))
				{
					// session required?
					$this->session['sess_required'] = isset($xml['stream:features'][0]['#']['session']);

					$this->send_xml("<iq type='set' id='bind_1'>
						<bind xmlns='urn:ietf:params:xml:ns:xmpp-bind'>
							<resource>" . utf8_htmlspecialchars($this->resource) . '</resource>
						</bind>
					</iq>');
					return $this->response($this->listen());
				}

				// Let's use TLS if SSL is not enabled and we can actually use it
				if (!$this->session['ssl'] && self::can_use_tls() && self::can_use_ssl() && isset($xml['stream:features'][0]['#']['starttls']))
				{
					$this->add_to_log('Switching to TLS.');
					$this->send_xml("<starttls xmlns='urn:ietf:params:xml:ns:xmpp-tls'/>\n");
					return $this->response($this->listen());
				}

				// Does the server support SASL authentication?

				// I hope so, because we do (and no other method).
				if (isset($xml['stream:features'][0]['#']['mechanisms'][0]['@']['xmlns']) && $xml['stream:features'][0]['#']['mechanisms'][0]['@']['xmlns'] == 'urn:ietf:params:xml:ns:xmpp-sasl')
				{
					// Now decide on method
					$methods = array();

					foreach ($xml['stream:features'][0]['#']['mechanisms'][0]['#']['mechanism'] as $value)
					{
						$methods[] = $value['#'];
					}

					// we prefer DIGEST-MD5
					// we don't want to use plain authentication (neither does the server usually) if no encryption is in place

					// http://www.xmpp.org/extensions/attic/jep-0078-1.7.html
					// The plaintext mechanism SHOULD NOT be used unless the underlying stream is encrypted (using SSL or TLS)
					// and the client has verified that the server certificate is signed by a trusted certificate authority.

					if (in_array('DIGEST-MD5', $methods))
					{
						$this->send_xml("<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' mechanism='DIGEST-MD5'/>");
					}
					else if (in_array('PLAIN', $methods) && ($this->session['ssl'] || !empty($this->session['tls'])))
					{
						// http://www.ietf.org/rfc/rfc4616.txt (PLAIN SASL Mechanism)
						$this->send_xml("<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' mechanism='PLAIN'>"
							. base64_encode($this->username . '@' . $this->server . chr(0) . $this->username . chr(0) . $this->password) .
							'</auth>');
					}
					else if (in_array('ANONYMOUS', $methods))
					{
						$this->send_xml("<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' mechanism='ANONYMOUS'/>");
					}
					else
					{
						// not good...
						$this->add_to_log('Error: No authentication method supported.');
						$this->disconnect();
						return false;
					}

					return $this->response($this->listen());
				}
				else
				{
					// ok, this is it. bye.
					$this->add_to_log('Error: Server does not offer SASL authentication.');
					$this->disconnect();
					return false;
				}
			break;

			case 'challenge':
				// continue with authentication...a challenge literally -_-
				$decoded = base64_decode($xml['challenge'][0]['#']);
				$decoded = $this->parse_data($decoded);

				if (!isset($decoded['digest-uri']))
				{
					$decoded['digest-uri'] = 'xmpp/'. $this->server;
				}

				// better generate a cnonce, maybe it's needed
				$decoded['cnonce'] = base64_encode(md5(uniqid(mt_rand(), true)));

				// second challenge?
				if (isset($decoded['rspauth']))
				{
					$this->send_xml("<response xmlns='urn:ietf:params:xml:ns:xmpp-sasl'/>");
				}
				else
				{
					// Make sure we only use 'auth' for qop (relevant for $this->encrypt_password())
					// If the <response> is choking up on the changed parameter we may need to adjust encrypt_password() directly
					if (isset($decoded['qop']) && $decoded['qop'] != 'auth' && strpos($decoded['qop'], 'auth') !== false)
					{
						$decoded['qop'] = 'auth';
					}

					$response = array(
						'username'	=> $this->username,
						'response'	=> $this->encrypt_password(array_merge($decoded, array('nc' => '00000001'))),
						'charset'	=> 'utf-8',
						'nc'		=> '00000001',
						'qop'		=> 'auth',			// only auth being supported
					);

					foreach (array('nonce', 'digest-uri', 'realm', 'cnonce') as $key)
					{
						if (isset($decoded[$key]))
						{
							$response[$key] = $decoded[$key];
						}
					}

					$this->send_xml("<response xmlns='urn:ietf:params:xml:ns:xmpp-sasl'>" . base64_encode($this->implode_data($response)) . '</response>');
				}

				return $this->response($this->listen());
			break;

			case 'failure':
				$this->add_to_log('Error: Server sent "failure".');
				$this->disconnect();
				return false;
			break;

			case 'proceed':
				// continue switching to TLS
				$meta = stream_get_meta_data($this->connection);
				stream_set_blocking($this->connection, 1);

				if (!stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
				{
					$this->add_to_log('Error: TLS mode change failed.');
					return false;
				}

				stream_set_blocking($this->connection, $meta['blocked']);
				$this->session['tls'] = true;

				// new stream
				$this->send_xml("<?xml version='1.0' encoding='UTF-8' ?" . ">\n");
				$this->send_xml("<stream:stream to='{$this->server}' xmlns='jabber:client' xmlns:stream='http://etherx.jabber.org/streams' version='1.0'>\n");

				return $this->response($this->listen());
			break;

			case 'success':
				// Yay, authentication successful.
				$this->send_xml("<stream:stream to='{$this->server}' xmlns='jabber:client' xmlns:stream='http://etherx.jabber.org/streams' version='1.0'>\n");
				$this->session['authenticated'] = true;

				// we have to wait for another response
				return $this->response($this->listen());
			break;

			case 'iq':
				// we are not interested in IQs we did not expect
				if (!isset($xml['iq'][0]['@']['id']))
				{
					return false;
				}

				// multiple possibilities here
				switch ($xml['iq'][0]['@']['id'])
				{
					case 'bind_1':
						$this->session['jid'] = $xml['iq'][0]['#']['bind'][0]['#']['jid'][0]['#'];

						// and (maybe) yet another request to be able to send messages *finally*
						if ($this->session['sess_required'])
						{
							$this->send_xml("<iq to='{$this->server}' type='set' id='sess_1'>
								<session xmlns='urn:ietf:params:xml:ns:xmpp-session'/>
								</iq>");
							return $this->response($this->listen());
						}

						return true;
					break;

					case 'sess_1':
						return true;
					break;

					case 'reg_1':
						$this->send_xml("<iq type='set' id='reg_2'>
								<query xmlns='jabber:iq:register'>
									<username>" . utf8_htmlspecialchars($this->username) . "</username>
									<password>" . utf8_htmlspecialchars($this->password) . "</password>
								</query>
							</iq>");
						return $this->response($this->listen());
					break;

					case 'reg_2':
						// registration end
						if (isset($xml['iq'][0]['#']['error']))
						{
							$this->add_to_log('Warning: Registration failed.');
							return false;
						}
						return true;
					break;

					case 'unreg_1':
						return true;
					break;

					default:
						$this->add_to_log('Notice: Received unexpected IQ.');
						return false;
					break;
				}
			break;

			case 'message':
				// we are only interested in content...
				if (!isset($xml['message'][0]['#']['body']))
				{
					return false;
				}

				$message['body'] = $xml['message'][0]['#']['body'][0]['#'];
				$message['from'] = $xml['message'][0]['@']['from'];

				if (isset($xml['message'][0]['#']['subject']))
				{
					$message['subject'] = $xml['message'][0]['#']['subject'][0]['#'];
				}
				$this->session['messages'][] = $message;
				return true;
			break;

			default:
				// hm...don't know this response
				$this->add_to_log('Notice: Unknown server response');
				return false;
			break;
		}
	}

	/**
	 * Send Jabber message
	 *
	 * @param string $to		Recepient usermane
	 * @param string $text		Message text
	 * @param string $subject	Message subject
	 * @param string $type		Message type
	 *
	 * @return int|bool
	 */
	public function send_message(string $to, string $text, string $subject = '', string $type = 'normal'): int|bool
	{
		if (!isset($this->session['jid']))
		{
			return false;
		}

		if (!in_array($type, array('chat', 'normal', 'error', 'groupchat', 'headline')))
		{
			$type = 'normal';
		}

		return $this->send_xml("<message from='" . utf8_htmlspecialchars($this->session['jid']) . "' to='" . utf8_htmlspecialchars($to) . "' type='$type' id='" . uniqid('msg') . "'>
			<subject>" . utf8_htmlspecialchars($subject) . "</subject>
			<body>" . utf8_htmlspecialchars($text) . "</body>
			</message>"
		);
	}

	/**
	 * Encrypts a password as in RFC 2831
	 *
	 * @param array $data Needs data from the client-server connection
	 * @return string
	 */
	public function encrypt_password(array $data): string
	{
		// let's me think about <challenge> again...
		foreach (array('realm', 'cnonce', 'digest-uri') as $key)
		{
			if (!isset($data[$key]))
			{
				$data[$key] = '';
			}
		}

		$pack = md5($this->username . ':' . $data['realm'] . ':' . $this->password);

		if (isset($data['authzid']))
		{
			$a1 = pack('H32', $pack)  . sprintf(':%s:%s:%s', $data['nonce'], $data['cnonce'], $data['authzid']);
		}
		else
		{
			$a1 = pack('H32', $pack)  . sprintf(':%s:%s', $data['nonce'], $data['cnonce']);
		}

		// should be: qop = auth
		$a2 = 'AUTHENTICATE:'. $data['digest-uri'];

		return md5(sprintf('%s:%s:%s:%s:%s:%s', md5($a1), $data['nonce'], $data['nc'], $data['cnonce'], $data['qop'], md5($a2)));
	}

	/**
	 * Parse data string like a="b",c="d",... or like a="a, b", c, d="e", f=g,...
	 *
	 * @param string $data
	 * @return array a => b ...
	 */
	public function parse_data(string $data): array
	{
		$data = explode(',', $data);
		$pairs = array();
		$key = false;

		foreach ($data as $pair)
		{
			$dd = strpos($pair, '=');

			if ($dd)
			{
				$key = trim(substr($pair, 0, $dd));
				$pairs[$key] = trim(trim(substr($pair, $dd + 1)), '"');
			}
			else if (strpos(strrev(trim($pair)), '"') === 0 && $key)
			{
				// We are actually having something left from "a, b" values, add it to the last one we handled.
				$pairs[$key] .= ',' . trim(trim($pair), '"');
				continue;
			}
		}

		return $pairs;
	}

	/**
	 * The opposite of jabber::parse_data()
	 *
	 * @param array $data Data array
	 * @return string
	 */
	public function implode_data(array $data): string
	{
		$return = array();
		foreach ($data as $key => $value)
		{
			$return[] = $key . '="' . $value . '"';
		}
		return implode(',', $return);
	}

	/**
	 * xmlize()
	 * @author Hans Anderson
	 * @copyright Hans Anderson / http://www.hansanderson.com/php/xml/
	 *
	 * @param string $data Data string
	 * @param string|int|bool $skip_white New XML parser option value
	 * @param string $encoding Encoding value
	 * @return array
	 */
	function xmlize(string $data, string|int|bool $skip_white = 1, string $encoding = 'UTF-8'): array
	{
		$data = trim($data);

		if (substr($data, 0, 5) != '<?xml')
		{
			// mod
			$data = '<root>'. $data . '</root>';
		}

		$vals = $index = $array = array();
		$parser = xml_parser_create($encoding);
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, $skip_white);
		xml_parse_into_struct($parser, $data, $vals, $index);
		xml_parser_free($parser);

		$i = 0;
		$tagname = $vals[$i]['tag'];

		$array[$tagname][0]['@'] = (isset($vals[$i]['attributes'])) ? $vals[$i]['attributes'] : array();
		$array[$tagname][0]['#'] = $this->_xml_depth($vals, $i);

		if (substr($data, 0, 5) != '<?xml')
		{
			$array = $array['root'][0]['#'];
		}

		return $array;
	}

	/**
	 * _xml_depth()
	 * @author Hans Anderson
	 * @copyright Hans Anderson / http://www.hansanderson.com/php/xml/
	 *
	 * @param array $vals XML data array
	 * @param int $i XML tags depth level
	 * @return array
	 */
	function _xml_depth(array $vals, int &$i): array
	{
		$children = array();

		if (isset($vals[$i]['value']))
		{
			array_push($children, $vals[$i]['value']);
		}

		while (++$i < count($vals))
		{
			switch ($vals[$i]['type'])
			{
				case 'open':

					$tagname = (isset($vals[$i]['tag'])) ? $vals[$i]['tag'] : '';
					$size = (isset($children[$tagname])) ? count($children[$tagname]) : 0;

					if (isset($vals[$i]['attributes']))
					{
						$children[$tagname][$size]['@'] = $vals[$i]['attributes'];
					}

					$children[$tagname][$size]['#'] = $this->_xml_depth($vals, $i);

				break;

				case 'cdata':
					array_push($children, $vals[$i]['value']);
				break;

				case 'complete':

					$tagname = $vals[$i]['tag'];
					$size = (isset($children[$tagname])) ? count($children[$tagname]) : 0;
					$children[$tagname][$size]['#'] = (isset($vals[$i]['value'])) ? $vals[$i]['value'] : array();

					if (isset($vals[$i]['attributes']))
					{
						$children[$tagname][$size]['@'] = $vals[$i]['attributes'];
					}

				break;

				case 'close':
					return $children;
				break;
			}
		}

		return $children;
	}
}
