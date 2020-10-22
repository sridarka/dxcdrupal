CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers

INTRODUCTION
------------


REQUIREMENTS
------------
The Drupal installation where Micro Site is enabled must be accessible from
a fully qualified domain name (or a local domain for development purpose).
Otherwise, if using 127.0.0.1 or localhost, Micro Site will not work and and
can produce fatal errors.

INSTALLATION
------------
Install as you would normally install a contributed Drupal module. See:
https://www.drupal.org/documentation/install/modules-themes/modules-8
for further information.


CONFIGURATION
-------------

Once Micro Site is enabled, you must immediately configure the three
parameters: base_url, base_scheme, public_url. The absence of these parameters
can lead to a 404 page on the primary instance's home page.

You can override theses settings in the settings.php file. For example:

@code
$config['micro_site.settings']['base_url'] = 'microsite.local';
$config['micro_site.settings']['base_scheme'] = 'http';
$config['micro_site.settings']['public_url'] = 'microsite.local';
@endcode

* Manage trusted host pattern per micro site *

Each micro site create automatically (when created) a file in the directory
DRUPAL_ROOT/sites/default/hosts with the corresponding trusted_host_patterns
setting.

You have to add to the settings.php file the following lines in order to include
these files.

@code
$dir_hosts = $app_root . '/' . $site_path . '/hosts';
if (file_exists($dir_hosts) && is_dir($dir_hosts)) {
  foreach (glob($dir_hosts . '/*.host.php') as $filename) {
    include $filename;
  }
}
@endcode

You can customize the trusted host pattern directory where each host file is
written using this setting in the project's settings.php. The default directory
used is DRUPAL_ROOT . '/sites/default/host'.

@code
$settings['micro_site.trusted_host_patterns_directory'] = '../config/hosts';
@endcode

Also, if you want manage trusted host pattern using a regex, for example using
a settings as below :

@code
$settings['trusted_host_patterns'] = [
  '^[a-z0-9]+\.factory\.org$',
];
@endcode

Then you can disable the generation of trusted host patterns file for each
micro_site created with this setting.

@code
$settings['micro_site.trusted_host_patterns_disabled'] = TRUE;
@endcode

* Manage reserved keywords for master *

By default keywords are reserved for the master host for subdomain. Theses
keywords are set arbitrarily :

@code
$keywords = ['host', 'localhost', 'www', 'dev', 'stage', 'preprod']
@endcode

For a master host configured with a base_url "microsite.local", this means that
a micro site can not have an URL as www.microsite.local, dev.microsite.local,
etc.

You can override this setting in the settings.php file of the project by using
the following code for example (or with an empty array to completely disable
this feature).

@code
$settings['micro_site.master_reserved_keywords'] = ['master'];
@endcode

DRUSH
-------------

Micro Site provides tree Drush commands which allow updates to Micro Site URL
and Scheme (for development purpose), given an array of settings if present
in the settings.php file. You can then quickly update the Micro Sites URL on
different environment (dev, stage, review, etc.). It is not recommended to run
theses commands on your production environment.

- drush micro-site-status : displays which Micro Site could be updated given
  the settings array
- drush micro-site-update : performs the update on the supplied Micro Site.
- drush micro-site-reset : performs a reset on the processed status of the
  Micro Site updated.

The array settings must have this structure.

@code
$settings['micro_site_override_url'] = [
  '3' => [
    'https' => FALSE,
    'site_url' => 'dev-demo3.microdrupal.com',
  ],
  'demo1.microdrupal.com' => [
    'https' => FALSE,
    'site_url' => 'dev-demo1.microdrupal.com',
  ],
  'generic' => [
    'method' => 'subdomain',
    'pattern' => 'dev',
    'https' => FALSE,
  ],
  'one_page' => [
    'method' => 'tld',
    'pattern' => 'local',
    'https' => FALSE,
  ],
  'global' => [
    'method' => 'prefix',
    'pattern' => 'dev',
    'https' => FALSE,
  ],
];
@endcode

You can set a new site url per micro site, with the existing site_url or the
site ID used as a key.
You can set a new URL on all the micro sites given a site type (generic,
one_page, etc.). The method can be "prefix" (concat the pattern to the domain /
subdomain), "subdomain" (replace the subdomain with the pattern) or "tld"
(replace the tld with the pattern).

The "global" entry lets you apply an update on *all* the micro sites which have
not match any other settings before.

The command drush micro-site-status lets you visualize for each Micro Site if it
will be updated and with which URL, given your settings array
"micro_site_override_url".


MAINTAINERS
-----------

Current maintainers:
 * flocondetoile - https://drupal.org/u/flocondetoile
