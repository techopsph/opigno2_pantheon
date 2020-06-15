<?php

declare(strict_types=1);

namespace PackageVersions;

use OutOfBoundsException;

/**
 * This class is generated by composer/package-versions-deprecated, specifically by
 * @see \PackageVersions\Installer
 *
 * This file is overwritten at every run of `composer install` or `composer update`.
 */
final class Versions
{
    const ROOT_PACKAGE_NAME = 'opigno/opigno-composer';
    /**
     * Array of all available composer packages.
     * Dont read this array from your calling code, but use the \PackageVersions\Versions::getVersion() method instead.
     *
     * @var array<string, string>
     * @internal
     */
    const VERSIONS          = array (
  'almende/vis' => 'v4.21.0@v4.21.0',
  'asm89/stack-cors' => '1.3.0@b9c31def6a83f84b4d4a40d35996d375755f0e08',
  'commerceguys/addressing' => 'v1.0.7@fa434c03e99a416de0b37d98068c6c0bdfd78cde',
  'commerceguys/intl' => 'v1.0.6@47d5d6d60d0cc25f867e337ce229a228bf6be6f8',
  'composer/installers' => 'v1.9.0@b93bcf0fa1fccb0b7d176b0967d969691cd74cca',
  'composer/package-versions-deprecated' => '1.8.0@98df7f1b293c0550bd5b1ce6b60b59bdda23aa47',
  'composer/semver' => '1.5.1@c6bea70230ef4dd483e6bbcab6005f682ed3a8de',
  'composer/xdebug-handler' => '1.4.2@fa2aaf99e2087f013a14f7432c1cd2dd7d8f1f51',
  'cweagans/composer-patches' => '1.6.7@2e6f72a2ad8d59cd7e2b729f218bf42adb14f590',
  'doctrine/annotations' => '1.10.3@5db60a4969eba0e0c197a19c077780aadbc43c5d',
  'doctrine/cache' => '1.10.1@35a4a70cd94e09e2259dfae7488afc6b474ecbd3',
  'doctrine/collections' => '1.6.5@fc0206348e17e530d09463fef07ba8968406cd6d',
  'doctrine/common' => '2.13.3@f3812c026e557892c34ef37f6ab808a6b567da7f',
  'doctrine/event-manager' => '1.1.0@629572819973f13486371cb611386eb17851e85c',
  'doctrine/inflector' => '1.4.3@4650c8b30c753a76bf44fb2ed00117d6f367490c',
  'doctrine/lexer' => '1.2.1@e864bbf5904cb8f5bb334f99209b48018522f042',
  'doctrine/persistence' => '1.3.7@0af483f91bada1c9ded6c2cfd26ab7d5ab2094e0',
  'doctrine/reflection' => '1.2.1@55e71912dfcd824b2fdd16f2d9afe15684cfce79',
  'dompdf/dompdf' => 'v0.8.5@6782abfc090b132134cd6cea0ec6d76f0fce2c56',
  'drupal-ckeditor-libraries-group/font' => '4.13.1@e596f49021e8c716c17f9f0345658ed14d8dbe1f',
  'drupal/address' => '1.8.0@8.x-1.8',
  'drupal/better_exposed_filters' => '4.0.0-beta1@8.x-4.0-beta1',
  'drupal/calendar' => '1.0.0-alpha2@8.x-1.0-alpha2',
  'drupal/calendar_datetime' => '1.0.0-alpha2@',
  'drupal/ckeditor_bgimage' => '1.1.0@8.x-1.1',
  'drupal/ckeditor_font' => 'dev-1.x@6d06753a6795cf7c2cd25ed7427b7602b43c6046',
  'drupal/commerce' => '2.18.0@8.x-2.18',
  'drupal/commerce_cart' => '2.18.0@',
  'drupal/commerce_checkout' => '2.18.0@',
  'drupal/commerce_log' => '2.18.0@',
  'drupal/commerce_number_pattern' => '2.18.0@',
  'drupal/commerce_order' => '2.18.0@',
  'drupal/commerce_payment' => '2.18.0@',
  'drupal/commerce_price' => '2.18.0@',
  'drupal/commerce_product' => '2.18.0@',
  'drupal/commerce_store' => '2.18.0@',
  'drupal/config_rewrite' => '1.3.0@8.x-1.3',
  'drupal/core' => '8.8.6@a5daf2aa45bbc72da72e1e64d5261f746ffb508c',
  'drupal/core-composer-scaffold' => '8.9.0@07cdfe2799789fc0c2d0e3e1ba64cb5e2a973ece',
  'drupal/core-project-message' => '8.9.0@e0e237d3da026a87784f70c1069345855340ec23',
  'drupal/ctools' => '3.4.0@8.x-3.4',
  'drupal/dropzonejs' => '2.1.0@8.x-2.1',
  'drupal/dropzonejs_eb_widget' => '2.1.0@',
  'drupal/embed' => '1.4.0@8.x-1.4',
  'drupal/entity' => '1.0.0@8.x-1.0',
  'drupal/entity_browser' => '1.10.0@8.x-1.10',
  'drupal/entity_browser_entity_form' => '1.10.0@',
  'drupal/entity_embed' => '1.1.0@8.x-1.1',
  'drupal/entity_print' => '2.1.0@8.x-2.1',
  'drupal/entity_reference_revisions' => '1.8.0@8.x-1.8',
  'drupal/field_group' => '3.0.0@8.x-3.0',
  'drupal/gnode' => '1.0.0-rc5@',
  'drupal/group' => '1.0.0-rc5@8.x-1.0-rc5',
  'drupal/h5p' => '1.0.0-rc17@8.x-1.0-rc17',
  'drupal/inline_entity_form' => '1.0.0-rc6@8.x-1.0-rc6',
  'drupal/jwt' => '1.0.0-beta3@8.x-1.0-beta3',
  'drupal/jwt_auth_consumer' => '1.0.0-beta3@',
  'drupal/jwt_auth_issuer' => '1.0.0-beta3@',
  'drupal/key' => '1.13.0@8.x-1.13',
  'drupal/mailsystem' => '4.3.0@8.x-4.3',
  'drupal/media_entity_browser' => 'dev-2.x@8129394',
  'drupal/message' => '1.0.0@8.x-1.0',
  'drupal/message_notify' => '1.1.0@8.x-1.1',
  'drupal/migrate_plus' => '5.1.0@8.x-5.1',
  'drupal/migrate_tools' => '5.0.0@8.x-5.0',
  'drupal/mimemail' => '1.0.0-alpha3@8.x-1.0-alpha3',
  'drupal/multiselect' => '1.0.0@8.x-1.0',
  'drupal/opigno_alter_entity_autocomplete' => '1.10.0@',
  'drupal/opigno_calendar' => '1.5.0@8.x-1.5',
  'drupal/opigno_calendar_event' => '1.4.0@8.x-1.4',
  'drupal/opigno_catalog' => '1.4.0@8.x-1.4',
  'drupal/opigno_certificate' => '1.6.0@8.x-1.6',
  'drupal/opigno_class' => '1.6.0@8.x-1.6',
  'drupal/opigno_commerce' => '1.3.0@8.x-1.3',
  'drupal/opigno_course' => '1.4.0@8.x-1.4',
  'drupal/opigno_dashboard' => '1.6.0@8.x-1.6',
  'drupal/opigno_forum' => '1.7.0@8.x-1.7',
  'drupal/opigno_group_manager' => '1.7.0@8.x-1.7',
  'drupal/opigno_ilt' => '1.5.0@8.x-1.5',
  'drupal/opigno_learning_path' => '1.10.0@8.x-1.10',
  'drupal/opigno_messaging' => '1.4.0@8.x-1.4',
  'drupal/opigno_migration' => '1.6.0@8.x-1.6',
  'drupal/opigno_mobile_app' => '1.3.0@8.x-1.3',
  'drupal/opigno_module' => '1.7.0@8.x-1.7',
  'drupal/opigno_module_group' => '1.7.0@',
  'drupal/opigno_moxtra' => '1.7.0@8.x-1.7',
  'drupal/opigno_notification' => '1.5.0@8.x-1.5',
  'drupal/opigno_scorm' => '1.7.0@8.x-1.7',
  'drupal/opigno_search' => '1.5.0@8.x-1.5',
  'drupal/opigno_statistics' => '1.6.0@8.x-1.6',
  'drupal/opigno_tincan_api' => '1.4.0@8.x-1.4',
  'drupal/opigno_tour' => '1.1.0@8.x-1.1',
  'drupal/pdf' => 'dev-1.x@ec8d13d',
  'drupal/platon' => '1.7.0@8.x-1.7',
  'drupal/popup_field_group' => '1.5.0@8.x-1.5',
  'drupal/private_message' => 'dev-2.x@0d76aa9d605841370865cfe8831b9b0faf4a6527',
  'drupal/profile' => '1.1.0@8.x-1.1',
  'drupal/restui' => '1.18.0@8.x-1.18',
  'drupal/role_delegation' => '1.1.0@8.x-1.1',
  'drupal/schemata' => '1.0.0-beta2@8.x-1.0-beta2',
  'drupal/schemata_json_schema' => '1.0.0-beta2@',
  'drupal/search_api' => '1.16.0@8.x-1.16',
  'drupal/search_api_db' => '1.16.0@',
  'drupal/state_machine' => '1.0.0@8.x-1.0',
  'drupal/tft' => '1.4.0@8.x-1.4',
  'drupal/token' => '1.7.0@8.x-1.7',
  'drupal/token_filter' => '1.2.0@8.x-1.2',
  'drupal/userprotect' => '1.1.0@8.x-1.1',
  'drupal/video' => '1.4.0@8.x-1.4',
  'drupal/view_mode_selector' => 'dev-1.x@93d105f',
  'drupal/views_role_based_global_text' => 'dev-1.x@550f8e0',
  'drupal/views_templates' => '1.1.0@8.x-1.1',
  'easyrdf/easyrdf' => '0.9.1@acd09dfe0555fbcfa254291e433c45fdd4652566',
  'egulias/email-validator' => '2.1.17@ade6887fd9bd74177769645ab5c474824f8a418a',
  'enyo/dropzone' => 'v5.5.0@origin/master',
  'firebase/php-jwt' => 'v5.2.0@feb0e820b8436873675fd3aca04f3728eb2185cb',
  'guzzlehttp/guzzle' => '6.5.4@a4a1b6930528a8f7ee03518e6442ec7a44155d9d',
  'guzzlehttp/promises' => 'v1.3.1@a59da6cf61d80060647ff4d3eb2c03a2bc694646',
  'guzzlehttp/psr7' => '1.6.1@239400de7a173fe9901b9ac7c06497751f00727a',
  'jean85/pretty-package-versions' => '1.3.0@e3517fb11b67e798239354fe8213927d012ad8f9',
  'kenwheeler/slick' => '1.8.1@',
  'masterminds/html5' => '2.7.1@a3edfe52f9e7380e498d33157e1330e85386645d',
  'mglaman/drupal-check' => '1.1.2@eaee2c8b03bf3bb8aff190b9000d12e0c3bea87b',
  'mglaman/phpstan-drupal' => '0.12.4@4a74b797251562081715bb086a49d460c61a8783',
  'mozilla/pdf.js' => 'dev-master@',
  'namshi/jose' => '7.2.3@89a24d7eb3040e285dd5925fcad992378b82bcff',
  'nette/finder' => 'v2.5.2@4ad2c298eb8c687dd0e74ae84206a4186eeaed50',
  'nette/neon' => 'v3.1.2@3c3dcbc6bf6c80dc97b1fc4ba9a22ae67930fc0e',
  'nette/utils' => 'v3.1.2@488f58378bba71767e7831c83f9e0fa808bf83b9',
  'opigno/opigno_lms' => '2.12.0@e965968782e2cc5926c2909d5af9d381f74d9f9e',
  'paragonie/random_compat' => 'v9.99.99@84b4dfb120c6f9b4ff7b3685f9b8f1aa365a0c95',
  'pear/archive_tar' => '1.4.9@c5b00053770e1d72128252c62c2c1a12c26639f0',
  'pear/console_getopt' => 'v1.4.3@a41f8d3e668987609178c7c4a9fe48fecac53fa0',
  'pear/pear-core-minimal' => 'v1.10.10@625a3c429d9b2c1546438679074cac1b089116a7',
  'pear/pear_exception' => 'v1.0.1@dbb42a5a0e45f3adcf99babfb2a1ba77b8ac36a7',
  'phenx/php-font-lib' => '0.5.2@ca6ad461f032145fff5971b5985e5af9e7fa88d8',
  'phenx/php-svg-lib' => 'v0.3.3@5fa61b65e612ce1ae15f69b3d223cb14ecc60e32',
  'phpstan/phpstan' => '0.12.29@9771daaf6b95c6313b908d0bcdee0afcd51f838a',
  'phpstan/phpstan-deprecation-rules' => '0.12.4@9b4b8851fb5d59fd0eed00fbe9c22cfc328e0187',
  'psr/container' => '1.0.0@b7ce3b176482dbbc1245ebf52b181af44c2cf55f',
  'psr/http-message' => '1.0.1@f6561bf28d520154e4b0ec72be95418abe6d9363',
  'psr/log' => '1.1.3@0f73288fd15629204f9d42b7055f72dacbe811fc',
  'ralouphie/getallheaders' => '3.0.3@120b605dfeb996808c31b6477290a714d356e822',
  'rusticisoftware/tincan' => '1.1.1@9758b3ec08653f7a49c8ab14ebd1f01557d89b78',
  'sabberworm/php-css-parser' => '8.3.1@d217848e1396ef962fb1997cf3e2421acba7f796',
  'stack/builder' => 'v1.0.6@a4faaa6f532c6086bc66c29e1bc6c29593e1ca7c',
  'symfony-cmf/routing' => '1.4.1@fb1e7f85ff8c6866238b7e73a490a0a0243ae8ac',
  'symfony/class-loader' => 'v3.4.42@e4636a4f23f157278a19e5db160c63de0da297d8',
  'symfony/console' => 'v3.4.42@bfe29ead7e7b1cc9ce74c6a40d06ad1f96fced13',
  'symfony/debug' => 'v3.4.42@518c6a00d0872da30bd06aee3ea59a0a5cf54d6d',
  'symfony/dependency-injection' => 'v3.4.42@e39380b7104b0ec538a075ae919f00c7e5267bac',
  'symfony/event-dispatcher' => 'v3.4.42@14d978f8e8555f2de719c00eb65376be7d2e9081',
  'symfony/http-foundation' => 'v3.4.42@fbd216d2304b1a3fe38d6392b04729c8dd356359',
  'symfony/http-kernel' => 'v3.4.42@6464a0475496040fe1f48428488d53e485be77a0',
  'symfony/polyfill-ctype' => 'v1.17.0@e94c8b1bbe2bc77507a1056cdb06451c75b427f9',
  'symfony/polyfill-iconv' => 'v1.17.0@c4de7601eefbf25f9d47190abe07f79fe0a27424',
  'symfony/polyfill-intl-idn' => 'v1.17.0@3bff59ea7047e925be6b7f2059d60af31bb46d6a',
  'symfony/polyfill-mbstring' => 'v1.17.0@fa79b11539418b02fc5e1897267673ba2c19419c',
  'symfony/polyfill-php56' => 'v1.17.0@e3c8c138280cdfe4b81488441555583aa1984e23',
  'symfony/polyfill-php70' => 'v1.17.0@82225c2d7d23d7e70515496d249c0152679b468e',
  'symfony/polyfill-php72' => 'v1.17.0@f048e612a3905f34931127360bdd2def19a5e582',
  'symfony/polyfill-util' => 'v1.17.0@4afb4110fc037752cf0ce9869f9ab8162c4e20d7',
  'symfony/process' => 'v3.4.42@8a895f0c92a7c4b10db95139bcff71bdf66d4d21',
  'symfony/psr-http-message-bridge' => 'v1.2.0@9ab9d71f97d5c7d35a121a7fb69f74fee95cd0ad',
  'symfony/routing' => 'v3.4.42@e0d43b6f9417ad59ecaa8e2f799b79eef417387f',
  'symfony/serializer' => 'v3.4.42@0db90db012b1b0a04fbb2d64ae9160871cad9d4f',
  'symfony/translation' => 'v3.4.42@b0cd62ef0ff7ec31b67d78d7fc818e2bda4e844f',
  'symfony/validator' => 'v3.4.42@5fb88120a11a75e17b602103a893dd8b27804529',
  'symfony/yaml' => 'v3.4.42@7233ac2bfdde24d672f5305f2b3f6b5d741ef8eb',
  'twig/twig' => 'v1.42.5@87b2ea9d8f6fd014d0621ca089bb1b3769ea3f8e',
  'typo3/phar-stream-wrapper' => 'v3.1.4@e0c1b495cfac064f4f5c4bcb6bf67bb7f345ed04',
  'webflo/drupal-finder' => '1.2.0@123e248e14ee8dd3fbe89fb5a733a6cf91f5820e',
  'webmozart/assert' => '1.8.0@ab2cb0b3b559010b75981b1bdce728da3ee90ad6',
  'webmozart/path-util' => '2.3.0@d939f7edc24c9a1bb9c0dee5cb05d8e859490725',
  'wikimedia/composer-merge-plugin' => 'dev-master@b6f3410a7c693dcafe4ad438a7a992ef92159bae',
  'willdurand/negotiation' => 'v2.3.1@03436ededa67c6e83b9b12defac15384cb399dc9',
  'zendframework/zend-diactoros' => '1.8.7@a85e67b86e9b8520d07e6415fcbcb8391b44a75b',
  'zendframework/zend-escaper' => '2.6.1@3801caa21b0ca6aca57fa1c42b08d35c395ebd5f',
  'zendframework/zend-feed' => '2.12.0@d926c5af34b93a0121d5e2641af34ddb1533d733',
  'zendframework/zend-stdlib' => '3.2.1@66536006722aff9e62d1b331025089b7ec71c065',
  'alchemy/zippy' => '0.4.9@59fbeefb9a249122867ef25e53addfcce31850d7',
  'chi-teck/drupal-code-generator' => '1.32.0@0e045f7a7e747af3d8f603156bf4d73be5768246',
  'consolidation/annotated-command' => '2.12.0@512a2e54c98f3af377589de76c43b24652bcb789',
  'consolidation/config' => '1.2.1@cac1279bae7efb5c7fb2ca4c3ba4b8eb741a96c1',
  'consolidation/filter-via-dot-access-data' => '1.0.0@a53e96c6b9f7f042f5e085bf911f3493cea823c6',
  'consolidation/log' => '1.1.1@b2e887325ee90abc96b0a8b7b474cd9e7c896e3a',
  'consolidation/output-formatters' => '3.5.0@99ec998ffb697e0eada5aacf81feebfb13023605',
  'consolidation/robo' => '1.4.12@eb45606f498b3426b9a98b7c85e300666a968e51',
  'consolidation/self-update' => '1.2.0@dba6b2c0708f20fa3ba8008a2353b637578849b4',
  'consolidation/site-alias' => '3.0.1@fd40a03f80f8fd4684b10bef8c8c4ec5a9a9bf26',
  'consolidation/site-process' => '2.1.0@f3211fa4c60671c6f068184221f06f932556e443',
  'container-interop/container-interop' => '1.2.0@79cbf1341c22ec75643d841642dd5d6acd83bdb8',
  'dflydev/dot-access-configuration' => 'v1.0.3@2e6eb0c8b8830b26bb23defcfc38d4276508fc49',
  'dflydev/dot-access-data' => 'v1.1.0@3fbd874921ab2c041e899d044585a2ab9795df8a',
  'dflydev/placeholder-resolver' => 'v1.0.2@c498d0cae91b1bb36cc7d60906dab8e62bb7c356',
  'dnoegel/php-xdg-base-dir' => 'v0.1.1@8f8a6e48c5ecb0f991c2fdcf5f154a47d85f9ffd',
  'drupal/console' => '1.9.4@04522b687b2149dc1f808599e716421a20d50a5b',
  'drupal/console-core' => '1.9.4@cc6f50c6ac8199140224347c862df75fd2d2f5ed',
  'drupal/console-en' => '1.9.4@30813a832fdb1244e84cbcc012cd103d5e9d673d',
  'drupal/console-extend-plugin' => '0.9.3@ad8e52df34b2e78bdacfffecc9fe8edf41843342',
  'drush/drush' => '9.7.2@ab5e345a72c9187a7d770486a09691f6526826aa',
  'grasmash/expander' => '1.0.0@95d6037344a4be1dd5f8e0b0b2571a28c397578f',
  'grasmash/yaml-expander' => '1.4.0@3f0f6001ae707a24f4d9733958d77d92bf9693b1',
  'league/container' => '2.4.1@43f35abd03a12977a60ffd7095efd6a7808488c0',
  'nikic/php-parser' => 'v4.5.0@53c2753d756f5adb586dca79c2ec0e2654dd9463',
  'psy/psysh' => 'v0.10.4@a8aec1b2981ab66882a01cce36a49b6317dc3560',
  'stecman/symfony-console-completion' => '0.11.0@a9502dab59405e275a9f264536c4e1cb61fc3518',
  'symfony/config' => 'v3.4.42@cd61db31cbd19cbe4ba9f6968f13c9076e1372ab',
  'symfony/css-selector' => 'v3.4.42@9ccf6e78077a3fc1596e6c7b5958008965a11518',
  'symfony/dom-crawler' => 'v3.4.42@c3086a58a66b2a519c0b7ac80539a3727609ea9c',
  'symfony/filesystem' => 'v3.4.42@0f625d0cb1e59c8c4ba61abb170125175218ff10',
  'symfony/finder' => 'v3.4.42@5ec813ccafa8164ef21757e8c725d3a57da59200',
  'symfony/polyfill-php80' => 'v1.17.0@5e30b2799bc1ad68f7feb62b60a73743589438dd',
  'symfony/var-dumper' => 'v4.4.10@56b3aa5eab0ac6720dcd559fd1d590ce301594ac',
  'h5p/h5p-core' => '*@a2be0ea9e0e3d138edb9baf73f8a98b3218e91b6',
  'h5p/h5p-editor' => '*@a2be0ea9e0e3d138edb9baf73f8a98b3218e91b6',
  'opigno/opigno-composer' => 'dev-master@a2be0ea9e0e3d138edb9baf73f8a98b3218e91b6',
);

    private function __construct()
    {
    }

    /**
     * @throws OutOfBoundsException If a version cannot be located.
     *
     * @psalm-param key-of<self::VERSIONS> $packageName
     * @psalm-pure
     */
    public static function getVersion(string $packageName) : string
    {
        if (isset(self::VERSIONS[$packageName])) {
            return self::VERSIONS[$packageName];
        }

        throw new OutOfBoundsException(
            'Required package "' . $packageName . '" is not installed: check your ./vendor/composer/installed.json and/or ./composer.lock files'
        );
    }
}
