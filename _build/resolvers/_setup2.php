<?php
/** @var xPDOTransport $transport */
/** @var array $options */
/** @var modX $modx */
if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return false;
}

$modx =& $transport->xpdo;
$packages = [
    'Ace' => [
        'version' => '1.6.5-pl',
        'service_url' => 'modstore.pro',
    ],
    'AjaxForm' => [
        'version' => '1.1.9-pl',
        'service_url' => 'modstore.pro',
    ],
    'autoRedirector' => [
        'version' => '0.1.0-beta',
        'service_url' => 'modstore.pro',
    ],
    'controlErrorLog' => [
        'version' => '1.2.1-pl',
        'service_url' => 'modstore.pro',
    ],
    // 'MobileDetect' => [
    //     'version' => '2.0.0-pl',
    //     'service_url' => 'modstore.pro',
    // ],
    'MinifyX' => [
        'version' => '1.6.0-pl',
        'service_url' => 'modstore.pro',
    ],
    'mixedImage' => [
        'version' => '2.0.0-beta',
        'service_url' => 'modstore.pro',
    ],
    'phpThumbOn' => [
        'version' => '1.3.3-beta',
        'service_url' => 'modstore.pro',
    ],
    'pdoTools' => [
        'version' => '2.11.2-pl',
        'service_url' => 'modstore.pro',
    ],
    'TVTable' => [
        'version' => '1.0.8-beta',
        'service_url' => 'modstore.pro',
    ],
    'tinyCompressor' => [
        'version' => '2.0.2-beta',
        'service_url' => 'modstore.pro',
    ],
    'ClientConfig' => [
        'version' => '2.0.0-pl',
        'service_url' => 'modx.com',
    ],
    'FormIt' => [
        'version' => '4.1.0-pl',
        'service_url' => 'modx.com',
        'author' => 'sterc'
    ],
    'MIGX' => [
        'version' => '2.11.0-pl',
        'service_url' => 'modx.com',
        'author' => 'Bruno17',
    ],
    'TinyMCE Rich Text Editor' => [
        'version' => '1.2.1-pl',
        'service_url' => 'modx.com',
    ],
    'translit' => [
        'version' => '1.0.0-beta',
        'service_url' => 'modx.com',
    ],
    'SEO Pro' => [
        'version' => '1.3.0-pl',
        'service_url' => 'modx.com',
    ],
    'SEO Tab' => [
        'version' => '2.1.0-pl',
        'service_url' => 'modx.com',
    ]
];

$downloadPackage = function ($src, $dst) {
    if (ini_get('allow_url_fopen')) {
        $file = @file_get_contents($src);
    } else {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $src);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            $safeMode = @ini_get('safe_mode');
            $openBasedir = @ini_get('open_basedir');
            if (empty($safeMode) && empty($openBasedir)) {
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            }

            $file = curl_exec($ch);
            curl_close($ch);
        } else {
            return false;
        }
    }
    
    if(!empty($file)) {
        return file_put_contents($dst, $file) == false ? false : true;    
    }
    return false;
};

$downloadedPackage = array();

// Качаем все пакеты
foreach ($packages as $packageName => $options){
    
    /** @var modTransportProvider $provider */
    if (!empty($options['service_url'])) {
        $provider = $modx->getObject('transport.modTransportProvider', [
            'service_url:LIKE' => '%'.$options['service_url'].'%',
        ]);
    }
    if (empty($provider)) {
        $provider = $modx->getObject('transport.modTransportProvider', 1);
    }
    $modx->getVersionData();
    $productVersion = $modx->version['code_name'] . '-' . $modx->version['full_version'];

    $response = $provider->request('package', 'GET', [
        'supports' => $productVersion,
        'query' => $packageName,
    ]);

    if (!empty($response)) {
        $foundPackages = simplexml_load_string($response->response);
        
        // print_r($foundPackages);
        $author = count($foundPackages) - 1;
        
        foreach ($foundPackages as $foundPackage) {
            /** @var modTransportPackage $foundPackage */
            /** @noinspection PhpUndefinedFieldInspection */
            if ($foundPackage->name == $packageName) {
                $sig = explode('-', $foundPackage->signature);
                $versionSignature = explode('.', $sig[1]);
                /** @noinspection PhpUndefinedFieldInspection */
                $url = $foundPackage->location;
                
                // Если нашли более 1 пакета, то фильтруем их по автору, если он указан
                if( $author && !empty($options['author']) && $foundPackage->author != $options['author'] ) {
                    continue;
                }
                
                // Качаем компонент
                $modx->log(modX::LOG_LEVEL_INFO, "Trying to download <b>{$packageName}</b>. Please wait...");
                if (!$downloadPackage($url, $modx->getOption('core_path') . 'packages/' . $foundPackage->signature . '.transport.zip')) {
                    $modx->log(modX::LOG_LEVEL_INFO, "Could not download package <b>{$packageName}</b>.");
                } else {
                    $downloadedPackage[$packageName] = array(
                        'created' => date('Y-m-d h:i:s'),
                        'provider' => $provider->get('id'),
                        'source' => $foundPackage->signature . '.transport.zip',
                        'version_major' => $versionSignature[0],
                        'version_minor' => !empty($versionSignature[1]) ? $versionSignature[1] : 0,
                        'version_patch' => !empty($versionSignature[2]) ? $versionSignature[2] : 0,
                        'signature' => $foundPackage->signature,
                        'sig' => $sig[2],
                        'version' => $options['version']
                    );
                    $modx->log(modX::LOG_LEVEL_INFO, "<b>{$packageName}</b> was successfully download");
                }
            }
        }
    } else {
        $modx->log(modX::LOG_LEVEL_INFO, "Could not find <b>{$packageName}</b> in MODX repository");
    }

}

// Устанавливаем пакеты
$installPackage = function ($packageName, $options = []) use ($modx) {
    
    // Add in the package as an object so it can be upgraded
    /** @var modTransportPackage $package */
    $package = $modx->newObject('transport.modTransportPackage');
    $package->set('signature', $options['signature']);
    /** @noinspection PhpUndefinedFieldInspection */
    $package->fromArray([
        'created' => $options['created'],
        'updated' => null,
        'state' => 1,
        'workspace' => 1,
        'provider' => $options['provider'],
        'source' => $options['source'],
        'package_name' => $packageName,
        'version_major' => $options['version_major'],
        'version_minor' => $options['version_minor'],
        'version_patch' => $options['version_patch'],
    ]);

    if (!empty($options['sig'])) {
        $r = preg_split('/([0-9]+)/', $options['sig'], -1, PREG_SPLIT_DELIM_CAPTURE);
        if (is_array($r) && !empty($r)) {
            $package->set('release', $r[0]);
            $package->set('release_index', (isset($r[1]) ? $r[1] : '0'));
        } else {
            $package->set('release', $options['sig']);
        }
    }

    if ($package->save() && $package->install()) {
        return [
            'success' => 1,
            'message' => "<b>{$packageName}</b> was successfully installed",
        ];
    } else {
        return [
            'success' => 0,
            'message' => "Could not save package <b>{$packageName}</b>",
        ];
    }

    return true;
};

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        
        foreach ($downloadedPackage as $name => $data) {
            if (!is_array($data)) {
                $data = ['version' => $data];
            }
            $installed = $modx->getIterator('transport.modTransportPackage', ['package_name' => $name]);
            /** @var modTransportPackage $package */
            foreach ($installed as $package) {
                if ($package->compareVersion($data['version'], '<=')) {
                    continue(2);
                }
            }
            $modx->log(modX::LOG_LEVEL_INFO, "Trying to install <b>{$name}</b>. Please wait...");
            // $response = $installPackage($name, $data);
            // $level = $response['success']
            //     ? modX::LOG_LEVEL_INFO
            //     : modX::LOG_LEVEL_ERROR;
            // $modx->log($level, $response['message']);
        }
        break;

    case xPDOTransport::ACTION_UNINSTALL:
        break;
}

return true;