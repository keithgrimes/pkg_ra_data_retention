<?php

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\CMS\Mail\MailTemplate;

\defined('_JEXEC') or die;

return new class () implements ServiceProviderInterface {
  public function register(Container $container)
  {
    $container->set(
      InstallerScriptInterface::class,
      new class (
          $container->get(AdministratorApplication::class),
          $container->get(DatabaseInterface::class)
      ) implements InstallerScriptInterface {
        private AdministratorApplication $app;
        private DatabaseInterface $db;

        public function __construct(AdministratorApplication $app, DatabaseInterface $db)
        {
          $this->app = $app;
          $this->db  = $db;
        }

        public function install(InstallerAdapter $parent): bool
        {
          MailTemplate::deleteTemplate('com_ra_data_retention.logemailtemplate');
          $result = MailTemplate::createTemplate(
                        'com_ra_data_retention.logemailtemplate', 
                        'COM_RA_DATA_RETENTION_SENDMAIL_SUBJECT', 
                        'COM_RA_DATA_RETENTION_SENDMAIL_BODY',
                        array('name', 'p1', 'p2')
                    );

          return $result;  
        }

        public function update(InstallerAdapter $parent): bool
        {
          // you can use this logic to update any existing template
          if ($mailTemplate = MailTemplate::getTemplate('com_ra_data_retention.logemailtemplate', '')) {
              MailTemplate::deleteTemplate('com_ra_data_retention.logemailtemplate');
              $result = MailTemplate::createTemplate(
                            'com_ra_data_retention.logemailtemplate', 
                            'COM_RA_DATA_RETENTION_SENDMAIL_SUBJECT', 
                            'COM_RA_DATA_RETENTION_SENDMAIL_BODY',
                            array('name', 'p1', 'p2')
                        );
    /*          $result = MailTemplate::updateTemplate(
                            'com_ra_data_retention.logemailtemplate', 
                            'COM_RA_DATA_RETENTION_SENDMAIL_SUBJECT', 
                            'COM_RA_DATA_RETENTION_SENDMAIL_BODY',
                            array('name', 'p1', 'p2')
                       );
    */

          } else {
              $result = MailTemplate::createTemplate(
                            'com_ra_data_retention.logemailtemplate', 
                            'COM_RA_DATA_RETENTION_SENDMAIL_SUBJECT', 
                            'COM_RA_DATA_RETENTION_SENDMAIL_BODY',
                            array('name', 'p1', 'p2')
                        );
          }
          return $result;  
        }

        public function uninstall(InstallerAdapter $parent): bool
        {
          MailTemplate::deleteTemplate('com_ra_data_retention.logemailtemplate');
          return true;
        }

        public function preflight(string $type, InstallerAdapter $parent): bool
        {
          return true;
        }

        public function postflight(string $type, InstallerAdapter $parent): bool
        {
          return true;
        }

      }
    );
  }
};