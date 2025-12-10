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
    'roles'    => ['admin', 'it'],
    'url'      => 'https://jenkins-tng.elkschrems.co.at/job/HF-API%20Unternehmensdaten%20PROD%20Daily%20FULL/lastCompletedBuild/api/json',
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'hf-enterprise-quick' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://jenkins-tng.elkschrems.co.at/job/HF-API%20Unternehmensdaten%20PROD%20Daily%20QUICK/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'hf-hauseubergabe-ninox' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://jenkins-tng.elkschrems.co.at/job/HF-API%20Hausuebergabe%20Ninox%20PROD%20Daily/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'hf-hauseubergabe-documents' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://jenkins-tng.elkschrems.co.at/job/HF-API%20Hausuebergabe%20PROD%20Daily%20Documents/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'hf-hauseubergabe-pictures' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://jenkins-tng.elkschrems.co.at/job/HF-API%20Hausuebergabe%20PROD%20Daily%20Pictures/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'hf-maengelkostenanzeige' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://jenkins-tng.elkschrems.co.at/job/HF-API%20MaengelKostenAnzeige%20PROD%20Daily/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'hf-planbesprechung' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://jenkins-tng.elkschrems.co.at/job/HF-API%20Planbesprechung%20PROD%20Daily/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'hf-protokolle' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://jenkins-tng.elkschrems.co.at/job/HF-API%20Protokolle%20PROD%20Daily/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'hf-qualitaetsmanagement' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://jenkins-tng.elkschrems.co.at/job/HF-API%20Qualitaetsmanagement%20PROD%20Daily/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'hf-regieschein' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://jenkins-tng.elkschrems.co.at/job/HF-API%20Regieschein%20PROD%20Daily/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'hf-wochenbericht' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://jenkins-tng.elkschrems.co.at/job/HF-API%20Wochenbericht%20PROD%20Daily/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'elkbau-calc-prod' => [
    'roles'    => ['admin', 'it', 'guest'],
    'url'      => "https://jenkins.elkschrems.co.at/job/ELK%20BAU%20Calculation%20Tool%20PROD/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'html5app-ppl-prod' => [
    'roles'    => ['admin', 'it', 'guest'],
    'url'      => "https://jenkins.elkschrems.co.at/job/HTML5App%20RESTful%20-%20PROD/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'nc-vis' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://vis2.elk.at/ocs/v2.php/apps/serverinfo/api/v1/info",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_VIS_TOKEN']],
    'query'    => ['format' => 'json'],
    'headers'  => null
  ],
  'nc-lis2' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://lis2.elk.at/nextcloud/ocs/v2.php/apps/serverinfo/api/v1/info",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_LIS2_TOKEN']],
    'query'    => ['format' => 'json'],
    'headers'  => null
  ],
  'nc-fileshare' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://fileshare.elk.at/ocs/v2.php/apps/serverinfo/api/v1/info",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_FLS_TOKEN']],
    'query'    => ['format' => 'json'],
    'headers'  => null
  ],
  'nc-kpat-prod' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://kundenportal.elk.at/ocs/v2.php/apps/serverinfo/api/v1/info",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_KPAT_PRD_TOKEN']],
    'query'    => ['format' => 'json'],
    'headers'  => null
  ],
  'nc-kpat-stage' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://kis-stage.elk.at/nextcloud/ocs/v2.php/apps/serverinfo/api/v1/info",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_KPAT_STG_TOKEN']],
    'query'    => ['format' => 'json'],
    'headers'  => null
  ],
  'nc-kpde-prod' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://kundenportal.elkhaus.de/nextcloud/ocs/v2.php/apps/serverinfo/api/v1/info",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_KPDE_PRD_TOKEN']],
    'query'    => ['format' => 'json'],
    'headers'  => null
  ],
  'nc-kpde-stage' => [
    'roles'    => ['admin', 'it'],
    'url'      => "https://kis-stage.elkhaus.de/nextcloud/ocs/v2.php/apps/serverinfo/api/v1/info",
    'method'   => 'GET',
    'auth'     => ['type' => 'nextcloud', 'token' => $secrets['NC_KPDE_STG_TOKEN']],
    'query'    => ['format' => 'json'],
    'headers'  => null
  ],
  'moodle' => [
    'roles'    => ['admin', 'it', 'guest'],
    'url'      => 'https://moodle.elk.at/webservice/rest/server.php',
    'method'   => 'GET',
    'auth'     => null,
    'headers'  => null,
    'query'    => [
      'wstoken' => $secrets['MOODLE_API_TOKEN'],
      'wsfunction' => 'core_webservice_get_site_info',
      'moodlewsrestformat' => 'json'
    ]
  ],
  'odoo-prod' => [
    'roles'    => ['admin', 'it', 'guest'],
    'url'      => 'https://odoo-elk.elkschrems.co.at/web/webclient/version_info',
    'method'   => 'POST',
    'body'     => ['json' => ['jsonrpc' => '2.0', 'method' => 'call', 'params' => []]],
    'auth'     => null,
    'query'    => null,
    'headers'  => ['Content-Type: application/json']
  ],
  'rex-elk-at-prod' => [
    'roles'    => ['admin', 'it'],
    'url'      => 'https://www.elk.at/api/serverinfo',
    'method'   => 'GET',
    'body'     => null,
    'auth'     => null,
    'query'    => null,
    'headers'  => ['Rex-Token: ' . $secrets['REX_TOKEN']]
  ],
  'rex-elkhaus-de-prod' => [
    'roles'    => ['admin', 'it'],
    'url'      => 'https://www.elkhaus.de/api/serverinfo',
    'method'   => 'GET',
    'body'     => null,
    'auth'     => null,
    'query'    => null,
    'headers'  => ['Rex-Token: ' . $secrets['REX_TOKEN']]
  ],
  'odoo-prod-pipeline-check' => [
    'roles'    => ['admin', 'it', 'guest'],
    'url'      => 'https://jenkins-tng.elkschrems.co.at/job/odoo_freebsd_prov_run_prod_v13/lastCompletedBuild/api/json',
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'odoo-prod-pipeline-transport' => [
    'roles'    => ['admin', 'it', 'guest'],
    'url'      => 'http://jenkins.sec.elkschrems.co.at/job/Provisionsabrechnung/lastCompletedBuild/api/json',
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'lis-legacy-pp' => [
    'roles'    => ['admin', 'it', 'guest'],
    'url'      => "https://jenkins.elkschrems.co.at/job/KisplanKonsole%20Daily/lastCompletedBuild/api/json",
    'method'   => 'GET',
    'auth'     => ['type' => 'jenkins'],
    'query'    => "tree={$jenkinsTree}",
    'headers'  => ['Accept: application/json']
  ],
  'lis-legacy-status' => [
    'roles'    => ['admin', 'it', 'guest'],
    'url'      => "https://lis.elk.at/status.php",
    'method'   => 'GET',
    'auth'     => null,
    'query'    => null,
    'headers'  => ['My-Token: ' . $secrets['LIS_LEGACY_TOKEN']]
  ],
  'pr-alec2-vtiger' => [
    'roles'    => ['admin'],
    'url'      => "https://prometheus.elk.at/api/v1/query",
    'method'   => 'GET',
    'auth'     => ['type' => 'basic', 'user' => $secrets['PR_USER'], 'pass' => $secrets['PR_PASSWORD']],
    'query'    => '?query={__name__=~"vtiger:disk:(size_bytes|avail_bytes|used_bytes|use_pctage)"}',
    'headers'  => ['Accept' => 'application/json']
  ],
  'pr-alec2-moodle' => [
    'roles'    => ['admin'],
    'url'      => "https://prometheus.elk.at/api/v1/query",
    'method'   => 'GET',
    'auth'     => ['type' => 'basic', 'user' => $secrets['PR_USER'], 'pass' => $secrets['PR_PASSWORD']],
    'query'    => '?query={__name__=~"moodle:disk:(size_bytes|avail_bytes|used_bytes|use_pctage)"}',
    'headers'  => ['Accept' => 'application/json']
  ]
  // Basic-Auth über user+pass (proxy verwendet CURLOPT_USERPWD)
  // 'jenkins' => [
  //  'url'      => "{$jenkinsTngBase}/some/api",
  //  'method'   => 'GET',
  //  'auth'     => ['type' => 'basic', 'user' => $secrets['JENKINS_USER'], 'pass' => $secrets['JENKINS_TOKEN']],
  //  'query'    => 'foo=bar&x=1',
  //  'headers'  => ['Accept' => 'application/json'],
  //  ,
  //],
  
  // Bearer-Token aus secrets
  //'other-api' => [
  //  'url'      => 'https://api.other/health',
  //  'method'   => 'GET',
  //  'auth'     => ['type' => 'bearer', 'token' => $secrets['OTHER_API_TOKEN']],
  //  'query'    => ['format' => 'json', 'foo' => 'bar'],
  //  'verifySSL' => true,
  //],
  
  // JSON-POST
  //'create-user' => [
  //  'url'      => 'https://api.example.com/users',
  //  'method'   => 'POST',
  //  'body'     => ['json' => ['name' => 'Alice', 'role' => 'admin']],
  //  'headers'  => ['Authorization' => 'Bearer ' . $secrets['API_TOKEN']],
  //],
  
  // Form-POST
  //'login' => [
  //  'url'      => 'https://api.example.com/login',
  //  'method'   => 'POST',
  //  'body'     => ['form' => ['username' => 'u', 'password' => 'p']],
  //],
  
  //Raw-POST (z. B. XML)
  //'send-xml' => [
  //  'url'      => 'https://api.example.com/xml',
  //  'method'   => 'POST',
  //  'body'     => ['raw' => '<req><id>123</id></req>'],
  //  'headers'  => ['Content-Type' => 'application/xml'],
  //],
  
  //Passthrough vom Client (Body durchreichen)
  //'proxy-post' => [
  //  'url'             => 'https://api.example.com/echo',
  //  'method'          => 'POST',
  //  'passthroughBody' => true,  // Body aus php://input übernehmen
  //]
];
