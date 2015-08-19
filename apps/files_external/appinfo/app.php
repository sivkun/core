<?php
/**
 * @author Christian Berendt <berendt@b1-systems.de>
 * @author fibsifan <fi@volans.uberspace.de>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author j-ed <juergen@eisfair.org>
 * @author Martin Mattel <martin.mattel@diemattels.at>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Philipp Kapfer <philipp.kapfer@gmx.at>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
 * @author Ross Nicoll <jrn@jrn.me.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
 * @author Volkan Gezer <volkangezer@gmail.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

OC::$CLASSPATH['OC\Files\Storage\StreamWrapper'] = 'files_external/lib/streamwrapper.php';
OC::$CLASSPATH['OC\Files\Storage\FTP'] = 'files_external/lib/ftp.php';
OC::$CLASSPATH['OC\Files\Storage\OwnCloud'] = 'files_external/lib/owncloud.php';
OC::$CLASSPATH['OC\Files\Storage\Google'] = 'files_external/lib/google.php';
OC::$CLASSPATH['OC\Files\Storage\Swift'] = 'files_external/lib/swift.php';
OC::$CLASSPATH['OC\Files\Storage\SMB'] = 'files_external/lib/smb.php';
OC::$CLASSPATH['OC\Files\Storage\AmazonS3'] = 'files_external/lib/amazons3.php';
OC::$CLASSPATH['OC\Files\Storage\Dropbox'] = 'files_external/lib/dropbox.php';
OC::$CLASSPATH['OC\Files\Storage\SFTP'] = 'files_external/lib/sftp.php';
OC::$CLASSPATH['OC_Mount_Config'] = 'files_external/lib/config.php';
OC::$CLASSPATH['OCA\Files\External\Api'] = 'files_external/lib/api.php';

require_once __DIR__ . '/../3rdparty/autoload.php';

// register Application object singleton
\OC_Mount_Config::$app = new \OCA\Files_external\Appinfo\Application();
$appContainer = \OC_Mount_Config::$app->getContainer();

$l = \OC::$server->getL10N('files_external');

OCP\App::registerAdmin('files_external', 'settings');
if (OCP\Config::getAppValue('files_external', 'allow_user_mounting', 'yes') == 'yes') {
	OCP\App::registerPersonal('files_external', 'personal');
}

\OCA\Files\App::getNavigationManager()->add([
	"id" => 'extstoragemounts',
	"appname" => 'files_external',
	"script" => 'list.php',
	"order" => 30,
	"name" => $l->t('External storage')
]);

// connecting hooks
OCP\Util::connectHook('OC_Filesystem', 'post_initMountPoints', '\OC_Mount_Config', 'initMountPointsHook');

OC_Mount_Config::registerBackend('\OC\Files\Storage\AmazonS3', [
	'backend' => (string)$l->t('Amazon S3'),
	'priority' => 100,
	'configuration' => [
		'key' => (string)$l->t('Key'),
		'secret' => '*'.$l->t('Secret'),
		'bucket' => (string)$l->t('Bucket'),
	],
	'has_dependencies' => true,
]);

OC_Mount_Config::registerBackend('\OC\Files\Storage\AmazonS3', [
	'backend' => (string)$l->t('Amazon S3 and compliant'),
	'priority' => 100,
	'configuration' => [
		'key' => (string)$l->t('Access Key'),
		'secret' => '*'.$l->t('Secret Key'),
		'bucket' => (string)$l->t('Bucket'),
		'hostname' => '&'.$l->t('Hostname'),
		'port' => '&'.$l->t('Port'),
		'region' => '&'.$l->t('Region'),
		'use_ssl' => '!'.$l->t('Enable SSL'),
		'use_path_style' => '!'.$l->t('Enable Path Style')
	],
	'has_dependencies' => true,
]);

OC_Mount_Config::registerBackend('\OC\Files\Storage\Dropbox', [
	'backend' => 'Dropbox',
	'priority' => 100,
	'configuration' => [
		'configured' => '#configured',
		'app_key' => (string)$l->t('App key'),
		'app_secret' => '*'.$l->t('App secret'),
		'token' => '#token',
		'token_secret' => '#token_secret'
	],
	'custom' => 'dropbox',
	'has_dependencies' => true,
]);

OC_Mount_Config::registerBackend('\OC\Files\Storage\Google', [
	'backend' => 'Google Drive',
	'priority' => 100,
	'configuration' => [
		'configured' => '#configured',
		'client_id' => (string)$l->t('Client ID'),
		'client_secret' => '*'.$l->t('Client secret'),
		'token' => '#token',
	],
	'custom' => 'google',
	'has_dependencies' => true,
]);

$mountProvider = $appContainer->query('OCA\Files_External\Config\ConfigAdapter');
\OC::$server->getMountProviderCollection()->registerProvider($mountProvider);
