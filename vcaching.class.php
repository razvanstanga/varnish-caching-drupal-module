<?php

/**
 * @file
 * VCaching class.
 */

/**
 * VCaching Class.
 */
class VCaching {
  protected $prefix = '';
  protected $purgeUrls = array();
  protected $varnishIps = NULL;
  protected $varnishHosts = NULL;
  protected $dynamicHost = NULL;
  protected $ipsToHosts = array();
  protected $statsJsons = array();
  protected $purgeKey = NULL;
  protected $noticeMessage = '';
  protected $debug = 0;

  /**
   * Constructor.
   *
   * @param string $prefix
   */
  public function __construct($prefix)
  {
    $this->prefix = $prefix;
    $this->_setupIpsToHosts();
    $this->purgeKey = ($purge_key = trim(variable_get($this->prefix . 'purge_key'))) ? $purge_key : null;
  }

  /**
   * Sets up needed vars.
   *
   * @return void
   */
  private function _setupIpsToHosts()
  {
    $this->debug = variable_get($this->prefix . 'debug');
    $this->varnishIps = variable_get($this->prefix . 'ips');
    $this->varnishHosts = variable_get($this->prefix . 'hosts');
    $this->dynamicHost = variable_get($this->prefix . 'dynamicHost');
    $this->statsJsons = variable_get($this->prefix . 'stats_json_file');
    $varnish_ips = explode(',', $this->varnishIps);
    $varnish_hosts = explode(',', $this->varnishHosts);
    $stats_jsons = explode(',', $this->statsJsons);
    foreach ($varnish_ips as $key => $ip) {
      if (strpos($ip, ':')) {
        $_ip = explode(':', $ip);
        $ip = $_ip[0];
        $port = $_ip[1];
      }
      else {
        $port = 80;
      }
      $this->ipsToHosts[] = array(
        'ip' => $ip,
        'port' => $port,
        'host' => $this->dynamicHost ? $_SERVER['HTTP_HOST'] : $varnish_hosts[$key],
        'statsJson' => isset($stats_jsons[$key]) ? $stats_jsons[$key] : null
        );
    }

    $this->purgeKey = ($purge_key = trim(variable_get($this->prefix . 'purge_key'))) ? $purge_key : null;
  }

  /**
   * Purge cache.
   *
   * @return void
   */
  public function purgeCache()
  {
    $purge_urls = array_unique($this->purgeUrls);

    if (!empty($purge_urls)) {
      foreach($purge_urls as $url) {
        $this->purgeUrl($url);
      }
    }
  }

  /**
   * Fetches the notice message.
   *
   * @param boolean $console
   * @return string
   */
  public function getNoticeMessage($console = FALSE)
  {
    return ($console ? str_replace("<br />", "\n", $this->noticeMessage): $this->noticeMessage);
  }

  /**
   * Purges the given URL.
   *
   * @param string $url relative url
   * @return void
   */
  public function purgeUrl($url)
  {
    $p = parse_url($url);

    if (isset($p['path'])) {
      $path = $p['path'];
    }
    else {
      $path = '';
    }

    if (isset($p['query']) && ($p['query'] == 'vc-regex')) {
      $purgemethod = 'regex';
      $purgeurl = base_path() . '.*';
    }
    else {
      $purgemethod = 'default';
      $purgeurl = $path;
    }
    foreach ($this->ipsToHosts as $ip_to_host) {
      $headers = array('host' => $ip_to_host['host'], 'X-VC-Purge-Method' => $purgemethod, 'X-VC-Purge-Host' => $ip_to_host['host']);
      if (!is_null($this->purgeKey)) {
        $headers['X-VC-Purge-Key'] = $this->purgeKey;
      }
      $response = $this->_vcachingCachePurge($ip_to_host['ip'], $ip_to_host['port'], $purgeurl, $headers);
      if ($response['error'] == TRUE) {
        $this->noticeMessage .= 'Error ' . $response['message'];
      } else {
        $this->noticeMessage .= '<br />' . t('Trying to purge URL :') . $purgeurl;
        preg_match("/<title>(.*)<\/title>/i", $response['message'], $matches);
        $this->noticeMessage .= ' => <br /> ' . isset($matches[1]) ? ' => ' . $matches[1] : $response['message'];
        $this->noticeMessage .= '<br />';
        if ($this->debug) {
          $this->noticeMessage .= nl2br($response['message'], TRUE) . '<br />';
        }
      }
    }
  }

  /**
   * Stats TAB HTML generator.
   *
   * @return string
   */
  public function stats()
  {
    $html = '<fieldset class="form-wrapper" id="edit-general"><legend><span class="fieldset-legend">' . t('Stats') . '</span></legend>' . "\n";
    $html .= '<div class="fieldset-wrapper">' . "\n";
    if ($_GET['info'] == 1) {
      $html .= '<div class="block clearfix block-system">' . "\n";
      $html .= '<div class="block-content clearfix">' . "\n";
      $html .= '<h2>' . t('Setup information') .'</h2>' . "\n";
      $html .= '<br /><p>' . "\n";
      $html .= t('<strong>Short story</strong><br />You must generate by cronjob the JSON stats file. The generated files must be copied on the backend servers in the Drupal root folder.');
      $html .= '<br /><br />' . "\n";
      $html .= sprintf(t('<strong>Long story</strong><br />On every Varnish Cache server setup a cronjob that generates the JSON stats file :<br /> %1$s /path/to/be/set/filename.json # every 3 minutes.'), '*/3 * * * *     root   /usr/bin/varnishstat -1j >');
      $html .= '<br />' . "\n";
      $html .= t('The generated files must be copied on the backend servers in the Drupal root folder.');
      $html .= '<br />' . "\n";
      $html .= t("Use a different filename for each Varnish Cache server. After this is done, fill in the relative path to the files in Statistics JSONs on the Settings tab.");
      $html .= '<br /><br />' . "\n";
      $html .= t('Example 1 <br />If you have a single server, both Varnish Cache and the backend on it, use the following cronjob:');
      $html .= '<br />' . "\n";
      $html .= sprintf(t('%1$s /path/to/the/drupal/root/varnishstat.json # every 3 minutes.'), '*/3 * * * *     root   /usr/bin/varnishstat -1j >');
      $html .= '<br />' . "\n";
      $html .= t('Then fill in the relative path to the files in Statistics JSONs on the Settings tab :') . "\n";
      $html .= '<br />' . "\n";
      $html .= '<input type="text" size="100" value="/varnishstat.json" class="fluid form-text" />' . "\n";

      $html .= '<br /><br />' . "\n";
      $html .= t("Example 2 <br />You have 2 Varnish Cache Servers, and 3 backend servers. Setup the cronjob :");
      $html .= '<br />' . "\n";
      $html .= sprintf(t('VC Server 1 : %1$s # every 3 minutes.'), '*/3 * * * *     root   /usr/bin/varnishstat -1j > /root/varnishstat/server1_3389398cd359cfa443f85ca040da069a.json');
      $html .= '<br />' . "\n";
      $html .= sprintf(t('VC Server 2 : %1$s # every 3 minutes.'), '*/3 * * * *     root   /usr/bin/varnishstat -1j > /root/varnishstat/server2_3389398cd359cfa443f85ca040da069a.json');
      $html .= '<br />' . "\n";
      $html .= t('Copy the files on the backend servers in /path/to/drupal/root/varnishstat/ folder. Then fill in the relative path to the files in Statistics JSONs on the Settings tab :');
      $html .= '<br />' . "\n";

      $html .= '<input type="text" class="fluid form-text" size="100" value="/varnishstat/server1_3389398cd359cfa443f85ca040da069a.json,/varnishstat/server2_3389398cd359cfa443f85ca040da069a.json" />' . "\n";
      $html .= '</p>';
      $html .= '</div>' . "\n";
      $html .= '</div>' . "\n";
    }
    if (trim($this->statsJsons)) {
      $html .= '<div class="block clearfix block-system">' . "\n";
      $html .= '<div class="block-content clearfix"><p class="clearfix"><strong>Select server</strong></p>' . "\n";
      $html .= '<ul class="secondary-tabs links clearfix">' . "\n";
      foreach ($this->ipsToHosts as $server => $ip_to_host) {
        $html .= '<li class="links ' . (($server == 0) ? 'active' : '') . '"><a class="server nav-tab" href="#" server="' . $server . '">' . sprintf(t('Server %1$s'), $ip_to_host['ip']) . '</a></li>' . "\n";
      }
      $html .= '</ul>';
      $html .= '</div>' . "\n";
      $html .= '</div>' . "\n";

      foreach ($this->ipsToHosts as $server => $ip_to_host) {
        $html .= '<div style="display:' . (($server == 0) ? 'block' : 'none') . '" class="servers server_' . $server . '">';
        $html .= sprintf(t('Fetching stats for server %1$s'), $ip_to_host['ip']);
        $html .= '</div>' . "\n";
        $html .= '<script type="text/javascript">' . "\n";
        $html .= 'jQuery.getJSON("' . $ip_to_host['statsJson'] . '", function(data) {' . "\n";
        $html .= 'var server = \'.server_' . $server . '\'' . "\n";
        $html .= 'jQuery(server).html(\'\');' . "\n";
        $html .= 'jQuery(server).append(\'<div id="block-system-vcaching-help" class="block block-system"><div class="block-content clearfix"><p>' . sprintf(t('Stats for server %1$s generated on'), $ip_to_host['ip']) . ' \' + data.timestamp);' . " + '</p></div></div>'\n";
        $html .= 'jQuery(server).append(\'<table><thead><tr><th><strong>' . t('Description') . '</strong></th><th><strong>' . t('Value') . '</strong></th><th><strong>' . t('Key') .'</strong></th></tr></thead><tbody class="varnishstats_' . $server . '"></tbody></table>\')' . "\n";
        $html .= 'delete data.timestamp;' . "\n";
        $html .= 'jQuery.each(data, function(key, val) {' . "\n";
        $html .= 'jQuery(\'.varnishstats_' . $server . '\').append(\'<tr><td class="views-field views-field-title">\'+val.description+\'</td><td>\'+val.value+\'</td><td>\'+key+\'</td></tr>\');' . "\n";
        $html .= '});' . "\n";
        $html .= '});' . "\n";
        $html .= '</script>' . "\n";
      }
      $html .= '<script type="text/javascript">' . "\n";
      $html .= 'jQuery(\'a.server\').click(function(e){' . "\n";
      $html .= 'e.preventDefault();' . "\n";
      $html .= 'jQuery(\'li.links\').removeClass(\'active\');' . "\n";
      $html .= 'jQuery(this).parent().addClass(\'active\');' . "\n";
      $html .= 'jQuery(".servers").hide();' . "\n";
      $html .= 'jQuery(".server_" + jQuery(this).attr(\'server\')).show();' . "\n";
      $html .= '});' . "\n";
      $html .= '</script>' . "\n";
    }
    $html .= '</div>' . "\n";
    $html .= '</fieldset>' . "\n";
    return $html;
  }

  /**
   * The socket connection to Varnish.
   *
   * @param string $server_ip
   * @param string $server_port
   * @param string $path
   * @param array $headers
   *
   * @return array
   */
  private function _vcachingCachePurge($server_ip, $server_port, $path = '/.*', $headers)
  {
    $fp = fsockopen($server_ip, $server_port, $errno, $errstr, 2);
    if (!$fp) {
      return array('error' => TRUE, 'message' => $errstr . '(' . $errno . ')');
    }
    else {
      $out = "PURGE " . $path . " HTTP/1.0\n";
      foreach ($headers as $key => $value) {
        $out .= $key . ': ' . $value . "\n";
      }
      $out .= "Connection: Close\n\n";
      fwrite($fp, $out);
      $ret = "";
      while (!feof($fp)) {
        $ret .= fgets($fp, 128);
      }
      fclose($fp);
      return array('error' => FALSE, 'message' => $ret);
    }
  }

  /**
   * Creates the configuration zip archive.
   *
   * @param int $version
   */
  public function downloadConf($version)
  {
    $tmpfile = tempnam("tmp", "zip");
    $zip = new ZipArchive();
    $zip->open($tmpfile, ZipArchive::OVERWRITE);
    $files = array(
      'default.vcl' => TRUE,
      'LICENSE' => FALSE,
      'README.rst' => FALSE,
      'conf/acl.vcl' => TRUE,
      'conf/backend.vcl' => TRUE,
      'lib/bigfiles.vcl' => FALSE,
      'lib/bigfiles_pipe.vcl' => FALSE,
      'lib/cloudflare.vcl' => FALSE,
      'lib/mobile_cache.vcl' => FALSE,
      'lib/mobile_pass.vcl' => FALSE,
      'lib/purge.vcl' => TRUE,
      'lib/static.vcl' => FALSE,
      'lib/xforward.vcl' => FALSE,
    );
    foreach ($files as $file => $parse) {
      $filepath = __DIR__ . '/varnish-conf/v' . $version . '/' . $file;
      if ($parse) {
        $content = $this->_parseConfFile($version, $file, file_get_contents($filepath));
      }
      else {
        $content = file_get_contents($filepath);
      }
      $zip->addFromString($file, $content);
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmpfile));
    header('Content-Disposition: attachment; filename="varnish_v' . $version . '_conf.zip"');
    readfile($tmpfile);
    unlink($tmpfile);
    exit();
  }

  /**
   * Parses the config files.
   *
   * @param int $version
   * @param string $file
   * @param string $content
   *
   * @return string
   */
  private function _parseConfFile($version, $file, $content)
  {
    if ($file == 'default.vcl') {
      $logged_in_cookie = variable_get($this->prefix . 'cookie');
      $content = str_replace('c005492c65', $logged_in_cookie, $content);
    } elseif ($file == 'conf/backend.vcl') {
      if ($version == 3) {
        $content = "";
      } elseif ($version == 4) {
        $content = "import directors;\n\n";
      }
      $backend = array();
      $ips = variable_get($this->prefix . 'conf_backends');
      $ips = explode(',', $ips);
      $id = 1;
      foreach ($ips as $ip) {
        if (strstr($ip, ":")) {
          $_ip = explode(':', $ip);
          $ip = $_ip[0];
          $port = $_ip[1];
        } else {
          $port = 80;
        }
        $content .= "backend backend" . $id . " {\n";
        $content .= "\t.host = \"" . $ip . "\";\n";
        $content .= "\t.port = \"" . $port . "\";\n";
        $content .= "}\n";
        $backend[3] .= "\t{\n";
        $backend[3] .= "\t\t.backend = backend" . $id . ";\n";
        $backend[3] .= "\t}\n";
        $backend[4] .= "\tbackends.add_backend(backend" . $id . ");\n";
        $id++;
      }
      if ($version == 3) {
        $content .= "\ndirector backends round-robin {\n";
        $content .= $backend[3];
        $content .= "}\n";
        $content .= "\nsub vcl_recv {\n";
        $content .= "\tset req.backend = backends;\n";
        $content .= "}\n";
      } elseif ($version == 4) {
        $content .= "\nsub vcl_init {\n";
        $content .= "\tnew backends = directors.round_robin();\n";
        $content .= $backend[4];
        $content .= "}\n";
        $content .= "\nsub vcl_recv {\n";
        $content .= "\tset req.backend_hint = backends.backend();\n";
        $content .= "}\n";
      }
    } elseif ($file == 'conf/acl.vcl') {
      $acls = variable_get($this->prefix . 'conf_acls');
      $acls = explode(',', $acls);
      $content = "acl cloudflare {\n";
      $content .= "\t# set this ip to your Railgun IP (if applicable)\n";
      $content .= "\t# \"1.2.3.4\";\n";
      $content .= "}\n";
      $content .= "\nacl purge {\n";
      $content .= "\t\"localhost\";\n";
      $content .= "\t\"127.0.0.1\";\n";
      foreach ($acls as $acl) {
        $content .= "\t\"" . $acl . "\";\n";
      }
      $content .= "}\n";
    } elseif ($file == 'lib/purge.vcl') {
      $purge_key = variable_get($this->prefix . 'purge_key');
      $content = str_replace('ff93c3cb929cee86901c7eefc8088e9511c005492c6502a930360c02221cf8f4', $purge_key, $content);
    }
    return $content;
  }
}
