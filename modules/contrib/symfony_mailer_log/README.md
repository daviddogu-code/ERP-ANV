# Description

This module allows logging email messages sent by
[Drupal Symfony Mailer](https://www.drupal.org/project/symfony_mailer).
They are stored as Drupal content entities.

# Configuration

* Install and enable this module
* Go to the policy settings page of Drupal Symfony Mailer 
  (/admin/config/system/mailer)
* Edit the policy for which you wish to log emails (could be a specific mail, or
  the `*All*` policy). Add the "Log email" element to your policy.
* View the logged mails at Admin > Reports > Mail log
  (/admin/reports/symfony_mailer_log)
