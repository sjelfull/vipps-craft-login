<?php


namespace vippsas\login\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use yii\web\NotFoundHttpException;

class AssetController extends Controller
{
    protected array|int|bool $allowAnonymous = ['button'];

    public function actionButton(string $file) : Response
    {
        // Strip the filename for all other special characters than -, _ and .
        $stripped = preg_replace('/[^A-Za-z0-9\-\_\.]/', '', $file);
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'gfx' . DIRECTORY_SEPARATOR . 'buttons' . DIRECTORY_SEPARATOR;
        // Check if the file exist
        if(!file_exists($path . $stripped))
        {
            if(YII_ENV_DEV) throw new NotFoundHttpException("Asset \"" . $path . $stripped . "\" not found.");
            throw new NotFoundHttpException("Asset \"" . $file . "\" not found.");
        }

        // Return the file
        $response = new Response();
        return $response->sendFile($path . $stripped, $stripped, ['inline' => true]);
    }
}