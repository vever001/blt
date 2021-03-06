<?php

namespace Acquia\Blt\Robo\Commands\Drupal;

use Acquia\Blt\Robo\Blt;
use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\UserConfig;
use Acquia\Blt\Robo\Exceptions\BltException;
use Zumba\Amplitude\Amplitude;

/**
 * Defines commands in the "drupal:toggle:modules" namespace.
 */
class ToggleModulesCommand extends BltTasks {

  /**
   * Enables and uninstalls specified modules.
   *
   * You may define the environment for which modules should be toggled by
   * passing the --environment=[value] option to this command, setting the
   * 'environnment' environment variable, or defining environment in one of your
   * BLT configuration files.
   *
   * @command drupal:toggle:modules
   *
   * @aliases dtm toggle setup:toggle-modules
   *
   * @validateDrushConfig
   */
  public function toggleModules() {
    if ($this->input()->hasArgument('environment')) {
      $environment = $this->input()->getArgument('environment');
    }
    elseif ($this->getConfig()->has('environment')) {
      $environment = $this->getConfigValue('environment');
    }
    elseif (getenv('environment')) {
      $environment = getenv('environment');
    }

    if (isset($environment)) {
      // Enable modules.
      $enable_key = "modules.$environment.enable";
      $this->doToggleModules('pm-enable', $enable_key);

      // Uninstall modules.
      $disable_key = "modules.$environment.uninstall";
      $this->doToggleModules('pm-uninstall', $disable_key);
    }
    else {
      $this->say("Environment is unset. Skipping drupal:toggle:modules...");
    }
  }

  /**
   * Enables or uninstalls an array of modules.
   *
   * @param string $command
   *   The drush command to execute, e.g., pm-enable or pm-uninstall.
   * @param string $config_key
   *   The config key containing the array of modules.
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  protected function doToggleModules($command, $config_key) {
    $userConfig = new UserConfig(Blt::configDir());
    $eventInfo = $userConfig->getTelemetryUserData();

    if ($this->getConfig()->has($config_key)) {
      $this->say("Executing <comment>drush $command</comment> for modules defined in <comment>$config_key</comment>...");
      $modules = (array) $this->getConfigValue($config_key);
      $modules_list = implode(' ', $modules);
      $result = $this->taskDrush()
        ->drush("$command $modules_list")
        ->run();
      $exit_code = $result->getExitCode();
      $eventInfo['active'] = TRUE;
      $eventInfo['modules'] = md5($modules_list);
      Amplitude::getInstance()->queueEvent('toggle-modules', $eventInfo);
    }
    else {
      $exit_code = 0;
      $this->logger->info("$config_key is not set.");
      $eventInfo['active'] = FALSE;
      Amplitude::getInstance()->queueEvent('toggle-modules', $eventInfo);
    }

    if ($exit_code) {
      throw new BltException("Could not toggle modules listed in $config_key.");
    }
  }

}
