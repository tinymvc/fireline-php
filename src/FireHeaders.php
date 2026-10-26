<?php

namespace Spark\Fire;

use Spark\Http\Header;
use Spark\Http\Response;
use function in_array;

/** Cache separation for the HTML and JSON representations of a route. */
final class FireHeaders
{
    public static function apply(Response $response, bool $isFire, ?string $assetVersion = null): Response
    {
        $varyHeaders = [];
        $values = [];

        foreach ($response->getHeaders() as $name => $value) {
            if (strcasecmp($name, 'Vary') === 0) {
                $varyHeaders[] = $name;
                $values = [...$values, ...array_map('trim', explode(',', $value))];
            }
        }

        if (!in_array('*', $values, true)) {
            foreach ([Header::X_FIRELINE, Header::X_FIRELINE_PRELOAD, Header::X_FIRELINE_PARTIAL] as $header) {
                if (!in_array(strtolower($header), array_map('strtolower', $values), true)) {
                    $values[] = $header;
                }
            }
        }

        $vary = in_array('*', $values, true) ? '*'
            : implode(', ', array_unique(array_filter($values)));

        foreach ($varyHeaders ?: ['Vary'] as $name) {
            $response->setHeader($name, $vary);
        }

        if ($assetVersion !== null) {
            $response->setHeader(Header::X_FIRELINE_ASSET_VERSION, $assetVersion);
        }

        if ($isFire) {
            $response->noCache()->setHeader(Header::X_FIRELINE, '1');
        }

        return $response;
    }
}
