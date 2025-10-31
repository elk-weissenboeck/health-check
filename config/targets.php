<?php
/**
* targets.php (extracted)
*
* - Contains the whitelist of targets for proxy.php
* - 'timeout' entries were removed (proxy.php uses sensible defaults)
* - 'insecure' was replaced by 'verifySSL' (true = verify SSL)
* - auth supports basic (user+pass), basic via Authorization header, bearer, and custom headers
* - This file can reference $secrets and other variables defined earlier in proxy.php (it's included in that scope)
*/

$jenkinsTree  = 'result,duration,timestamp,building';

return [
  // Beispiel: Basic-Auth + SSL-Verify AUS + GET
  'hf-enterprise-full' => [
    'url'      => "https://jenkins-tng.elk.at/job/HF-API%20Unternehmensdaten%20PROD%20Daily%20FULL/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true  
    
  ],
  'hf-enterprise-quick' => [
    'url'      => "https://jenkins-tng.elk.at/job/HF-API%20Unternehmensdaten%20PROD%20Daily%20S5+S6/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'hf-hauseubergabe-ninox' => [
    'url'      => "https://jenkins-tng.elk.at/job/HF-API%20Hausuebergabe%20Ninox%20PROD%20Daily/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'hf-hauseubergabe-documents' => [
    'url'      => "https://jenkins-tng.elk.at/job/HF-API%20Hausuebergabe%20PROD%20Daily%20Documents/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'hf-hauseubergabe-pictures' => [
    'url'      => "https://jenkins-tng.elk.at/job/HF-API%20Hausuebergabe%20PROD%20Daily%20Pictures/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'hf-maengelkostenanzeige' => [
    'url'      => "https://jenkins-tng.elk.at/job/HF-API%20MaengelKostenAnzeige%20PROD%20Daily/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'hf-planbesprechung' => [
    'url'      => "https://jenkins-tng.elk.at/job/HF-API%20Planbesprechung%20PROD%20Daily/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'hf-protokolle' => [
    'url'      => "https://jenkins-tng.elk.at/job/HF-API%20Protokolle%20PROD%20Daily/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'hf-qualitaetsmanagement' => [
    'url'      => "https://jenkins-tng.elk.at/job/HF-API%20Qualitaetsmanagement%20PROD%20Daily/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'hf-regieschein' => [
    'url'      => "https://jenkins-tng.elk.at/job/HF-API%20Regieschein%20PROD%20Daily/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'hf-wochenbericht' => [
    'url'      => "https://jenkins-tng.elk.at/job/HF-API%20Wochenbericht%20PROD%20Daily/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'elkbau-calc-prod' => [
    'url'      => "https://jenkins.elk.at/job/ELK%20BAU%20Calculation%20Tool%20PROD/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'html5app-ppl-prod' => [
    'url'      => "https://jenkins.elk.at/job/HTML5App%20RESTful%20-%20PROD/lastCompletedBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'headers'  => ['Accept: application/json'],
    'verifySSL' => true
    
  ],
  'nc-vis' => [
    'url'      => "https://vis2.elk.at/ocs/v2.php/apps/serverinfo/api/v1/info?format=json",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_VIS_TOKEN']],
    'headers'  => null,
    'sslVerify' => true
    
  ],
  'nc-lis2' => [
    'url'      => "https://lis2.elk.at/nextcloud/ocs/v2.php/apps/serverinfo/api/v1/info?format=json",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_LIS2_TOKEN']],
    'headers'  => null,
    'sslVerify' => true
    
  ],
  'nc-fileshare' => [
    'url'      => "https://fileshare.elk.at/ocs/v2.php/apps/serverinfo/api/v1/info?format=json",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_FLS_TOKEN']],
    'headers'  => null,
    'sslVerify' => true
    
  ],
  'nc-kpat-prod' => [
    'url'      => "https://kundenportal.elk.at/ocs/v2.php/apps/serverinfo/api/v1/info?format=json",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_KPAT_PRD_TOKEN']],
    'headers'  => null,
    'sslVerify' => true
    
  ],
  'nc-kpat-stage' => [
    'url'      => "https://kis-stage.elk.at/nextcloud/ocs/v2.php/apps/serverinfo/api/v1/info?format=json",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_KPAT_STG_TOKEN']],
    'headers'  => null,
    'sslVerify' => true
    
  ],
  'nc-kpde-prod' => [
    'url'      => "https://kundenportal.elkhaus.de/nextcloud/ocs/v2.php/apps/serverinfo/api/v1/info?format=json",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_KPDE_PRD_TOKEN']],
    'headers'  => null,
    'sslVerify' => true
    
  ],
  'nc-kpde-stage' => [
    'url'      => "https://kis-stage.elkhaus.de/nextcloud/ocs/v2.php/apps/serverinfo/api/v1/info?format=json",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_KPDE_STG_TOKEN']],
    'headers'  => null,
    'sslVerify' => true
    
  ],
  'moodle' => [
    'url'      => 'https://moodle.elk.at/webservice/rest/server.php?wstoken='.$secrets['MOODLE_API_TOKEN'].'&wsfunction=core_webservice_get_site_info&moodlewsrestformat=json',
    'method'   => 'GET',
    'auth'     => null,
    'headers'  => null,
    'sslVerify' => true
    
  ],
  // Basic-Auth über user+pass (proxy verwendet CURLOPT_USERPWD)
  //['type' => 'jenkins'] => [
  //  'url'      => "{$jenkinsTngBase}/some/api",
  //  'method'   => 'GET',
  //  'auth'     => ['type' => 'basic', 'user' => $secrets['JENKINS_USER'], 'pass' => $secrets['JENKINS_TOKEN']],
  //  'headers'  => ['Accept' => 'application/json'],
  //  'verifySSL' => false,
  //],
  // Bearer-Token aus secrets
  //'other-api' => [
  //  'url'      => 'https://api.other/health',
  //  'method'   => 'GET',
  //  'auth'     => ['type' => 'bearer', 'token' => $secrets['OTHER_API_TOKEN']],
  //  'verifySSL' => true,
  //],
];