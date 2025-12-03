<?php

namespace Drupal\solr_sitemap\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for UMD Sitemap Collections.
 */
class SolrSitemapSettingsForm extends ConfigFormBase {

  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  const SETTINGS = 'solr_sitemap.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'solr-sitemap-settings-form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Load the stored values to populate forms.
    $config = $this->config(static::SETTINGS);

    $form['solr_sitemap_settings'] = [
      '#type' => 'item',
      '#markup' => '<h3>' . $this->t('YAML Configuration for UMD Collection Sitemaps') . '</h3>',
    ];

    $form['solr_sitemap_targets'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sitemap Targets'),
      '#description' => $this->t('YAML formatted Sitemap Targets. See the README.md file.'),
      '#default_value' => Yaml::dump($config->get('solr_sitemap_targets')),
      '#rows' => 7,
      '#cols' => 100,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $yaml_fields = ['solr_sitemap_targets'];

    foreach ($yaml_fields as $yfield) {
      $yfield_str = trim($form_state->getValue($yfield));

      // A starting line with "---" is required by the YAML parser, so add it,
      // if it is not present.
      if (!str_starts_with($yfield_str, "---")) {
        $yfield_str = "---\n" . $yfield_str;
      }
      $decoded_yfield = [];

      try {
        $decoded_yfield = Yaml::parse($yfield_str);
      }
      catch (ParseException $e) {
        $error_message = $form[$yfield]['#title'] . " has missing or invalid YAML.";
        $form_state->setErrorByName($yfield, $error_message);
        return;
      }

      if (count(array_keys($decoded_yfield)) == 0) {
        $error_message = $form[$yfield]['#title'] . " has missing or invalid YAML.";
        $form_state->setErrorByName($yfield, $error_message);
        return;
      }
      // Targets with missing or bad URLs.
      $targets_bad_urls = [];
      $error_message = "The 'url' field is missing or invalid for the following values " .
                       "(should have format 'https://DOMAIN/ENDPOINT?SEARCH_QUERY_PARAM='): ";
      foreach ($decoded_yfield as $name => $val) {
        if (!empty($val['url'])) {
          $url = $val['url'];
          if (filter_var($url, FILTER_VALIDATE_URL) == FALSE) {
            $targets_bad_urls[] = $name;
          }
          if (count($targets_bad_urls) > 0) {
            $targets_bad_urls_str = implode("'\n,'", $targets_bad_urls);
            $form_state->setErrorByName($yfield, $this->t($error_message) . "'$targets_bad_urls_str'");
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $yaml_fields = ['solr_sitemap_targets'];

    foreach ($yaml_fields as $yfield) {
      $yfield_str = $form_state->getValue($yfield);
      try {
        $y_values = Yaml::parse($yfield_str);
        $config->set($yfield, $y_values);
      }
      catch (ParseException $pe) {
        // Shouldn't happen, because invalid YAML should be caught by
        // "validateForm" method.
        $this->logger('solr_sitemap')->error("Error parsing 'Hero Search' YAML: " . $pe);
      }
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }
}
