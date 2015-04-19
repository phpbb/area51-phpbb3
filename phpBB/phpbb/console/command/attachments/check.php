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
namespace phpbb\console\command\attachments;

use phpbb\finder;
use phpbb\filesystem\filesystem as phpbb_filesystem;
use phpbb\config\config;
use phpbb\console\command\command;
use phpbb\mimetype\guesser;
use phpbb\db\driver\driver_interface;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class check extends command
{
	/**
	 * @var driver_interface
	 */
	protected $db;

	/**
	 * @var phpbb_filesystem
	 */
	protected $filesystem;

	/**
	 * @var config
	 */
	protected $config;

	/**
	 * @var guesser
	 */
	protected $mimetype_guesser;
	/**
	 * phpBB root path
	 * @var string
	 */
	protected $phpbb_root_path;

	/**
	 * Constructor
	 *
	 * @param \phpbb\user $user The user object (used to get language information)
	 * @param config $config
	 * @param driver_interface $db Database connection
	 * @param phpbb_filesystem $filesystem
	 * @param string $phpbb_root_path Root path
	 */
	public function __construct(\phpbb\user $user, config $config, driver_interface $db, phpbb_filesystem $filesystem, guesser $mimetype_guesser, $phpbb_root_path)
	{
		$this->config = $config;
		$this->db = $db;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->filesystem = $filesystem;
		$this->mimetype_guesser = $mimetype_guesser;

		parent::__construct($user);
	}

	/**
	 * Sets the command name and description
	 *
	 * @return null
	 */
	protected function configure()
	{
		$this
			->setName('attachments:check')
			->setDescription($this->user->lang('CLI_DESCRIPTION_ATTACHMENTS_RENAME'))
			->addOption('dry-run')
			->addOption('rename-all')
			->addOption('delete-thumbnails')
			->addOption('delete-orphans')
			->addOption('rename')
			->addOption('check-strings')
			->addOption('string-length', null, InputOption::VALUE_REQUIRED, '', 200)
			->addOption('display-max-string')
		;
	}

	/**
	 * Executes the command attachments:check.
	 *
	 * Rename all the attachments, compare their size with the one in the database and check the orphans.
	 * Note: all the thumbnails will be removed.
	 *
	 * @param InputInterface $input The input stream used to get the argument and verbose option.
	 * @param OutputInterface $output The output stream, used for printing verbose-mode and error information.
	 *
	 * @return int 0 if all is ok, 1 otherwise
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$dry_run = $input->getOption('dry-run');

		$return = 0;
		$results = array('thumbnails' => array(), 'attachments' => array(), 'attachments_corrupted' => array('size' => array(), 'filetime' => array(), 'mime_type' => array()), 'orphans' => array());

		$finder = new finder($this->filesystem, $this->phpbb_root_path);
		$finder->core_path($this->config['upload_path'] . '/');
		$files = $finder->get_files(false);

		$filesystem = new Filesystem();
		foreach ($files as $file)
		{
			$filename = basename($file);
			if (substr($filename, 0, strlen('thumb_')) === 'thumb_')
			{
				$results['thumbnails'][] = $file;

				if ($input->getOption('delete-thumbnails'))
				{
					if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE)
					{
						$output->writeln("<comment>Deletes {$filename} (thumbnail)</comment>");
					}

					if (!$dry_run)
					{
						$filesystem->remove($filename);
					}
				}
				else
				{
					if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE)
					{
						$output->writeln("<comment>Thumbnail: {$filename}</comment>");
					}
				}
			}
			else
			{
				$sql = 'SELECT *
							FROM ' . ATTACHMENTS_TABLE . "
							WHERE physical_filename = '" . $this->db->sql_escape($filename) . "'";
				$result = $this->db->sql_query($sql);

				if ($row = $this->db->sql_fetchrow($result))
				{
					$file_safe = true;

					$file_size = filesize($file);
					$filemtime = filemtime($file);
					$mimetype = $this->mimetype_guesser->guess($file, $row['real_filename']);

					if ($file_size !== (int) $row['filesize'])
					{
						$output->writeln("<error>File size dismatch for {$file} ({$row['real_filename']}): {$file_size} against {$row['filesize']}</error>");
						$results['attachments_corrupted']['size'][] = $file;
						$file_safe = false;
					}

					if ($filemtime !== (int) $row['filetime'])
					{
						$output->writeln("<error>File time dismatch for {$file} ({$row['real_filename']}): {$filemtime} against {$row['filetime']}</error>");
						$results['attachments_corrupted']['filemtime'][] = $file;
						$file_safe = false;
					}

					if ($mimetype !== $row['mimetype'])
					{
						$output->writeln("<error>Mime type dismatch for {$file} ({$row['real_filename']}): {$mimetype} against {$row['mimetype']}</error>");
						$results['attachments_corrupted']['mime_type'][] = $file;
						$file_safe = false;
					}

					if (substr($mimetype, 0, strlen('text/')) !== 'text/')
					{
						if ($this->check_strings($input, $output, $file) !== 0)
						{
							$file_safe = false;
						}
					}

					if (($file_safe && $input->getOption('rename')) || $input->getOption('rename-all'))
					{
						$results['attachments'][] = $file;
						$new_name = $row['poster_id'] . '_' . md5(unique_id());

						if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE)
						{
							$output->writeln("<comment>Renames {$filename} ({$row['real_filename']}) to {$new_name}</comment>");
						}

						if (!$dry_run)
						{
							$filesystem->rename($file, $this->phpbb_root_path . $this->config['upload_path'] . '/' . $new_name);
							$sql = 'UPDATE ' . ATTACHMENTS_TABLE . "
								SET physical_filename = '" . $this->db->sql_escape($new_name) . "'
								WHERE attach_id=" . $row['attach_id'];
							$this->db->sql_query($sql);
						}
					}
				}
				else
				{
					if ($input->getOption('delete-orphans'))
					{
						if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE)
						{
							$output->writeln("<info>Deletes: {$file} (orphan)</info>");
						}

						if (!$dry_run)
						{
							$filesystem->remove($filename);
						}
					}
					else if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE)
					{
						$output->writeln("<info>Orphan: {$file}</info>");
					}

					$results['orphans'][] = $file;
				}
			}
		}

		if (!$dry_run)
		{
			// Al the thumbnails have been removed
			$sql = 'UPDATE ' . ATTACHMENTS_TABLE . '
				SET thumbnail = 0';
			$this->db->sql_query($sql);
		}

		return $return;
	}

	/**
	 * Check the strings contains in a given file
	 *
	 * @param InputInterface $input The input stream used to get the argument and verbose option.
	 * @param OutputInterface $output The output stream, used for printing verbose-mode and error information.
	 * @param string $filename The filename to test
	 *
	 * @return int 0 if all is ok, 1 otherwise
	 */
	protected function check_strings(InputInterface $input, OutputInterface $output, $filename)
	{
		$length_limit = $input->getOption('string-length');
		$display_max_string = $input->getOption('display-max-string');
		$content = file_get_contents($filename);
		$length     = 0;
		$max_length = 0;
		$string     = '';
		$max_string = '';

		$res = 0;

		$content_length = strlen($content);
		for ($i = 0; $i < $content_length; $i++)
		{
			$byte = ord($content[$i]);
			if (($byte >= 32 && $byte <= 126) || $byte === 9 || $byte === 10 || $byte === 13)
			{
				$length++;
				$string .= chr($byte);
			}
			else
			{
				if ($length > $max_length)
				{
					$max_length = $length;
					$max_string = $string;
				}

				$length = 0;
				$string = '';
			}
		}

		if ($length > $max_length)
		{
			$max_length = $length;
			$max_string = $string;
		}

		if ($max_length >= $length_limit)
		{
			$res = 1;
			$details = '';
			if ($display_max_string)
			{
				$details = ": {$max_string}";
			}

			$output->writeln("<error>Long ascii string ({$max_length}) found in {$filename}{$details}</error>");
		}

		return $res;
	}
}
