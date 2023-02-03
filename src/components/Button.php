<?php


namespace vippsas\login\components;

use Craft;
use craft\helpers\StringHelper;

class Button
{
    // Constants
    // =========================================================================

    /**
     * @var integer
     */
    const SIZE_SMALL = 210;

    /**
     * @var integer
     */
    const SIZE_LARGE = 250;

    /**
     * @var string
     */
    const SHAPE_PILL = 'pill';

    /**
     * @var string
     */
    const SHAPE_RECTANGLE = 'rect';

    /**
     * @var string
     */
    const LANG_ENGLISH = 'EN';

    /**
     * @var string
     */
    const LANG_NORWEGIAN = 'NO';

    /**
     * @var string
     */
    const TYPE_LOGIN = 'log_in_with';

    /**
     * @var string
     */
    const TYPE_CONTINUE = 'continue_with';

    /**
     * @var string
     */
    const TYPE_REGISTER = 'register_with';

    // Properties
    // =========================================================================

    private int $size = self::SIZE_LARGE;
    private string $shape = self::SHAPE_RECTANGLE;
    private string $lang;
    private string $href;
    private string $type = self::TYPE_LOGIN;
    private string $return_url;

    // Public Methods
    // =========================================================================

    public function __construct()
    {
        $this->href = Craft::$app->request->getHostInfo().'/vipps/redirect/login';
    }

    public function render(string $a = null, string $img = null) : string
    {
        Craft::$app->user->setReturnUrl(Craft::$app->request->getUrl());

        // If the button language is not given, try to set the language based on the site language
        if(!isset($this->lang))
        {
            if(in_array(Craft::$app->sites->currentSite->language, ['nb', 'nn', 'nb-NO', 'nn-NO'])) $this->lang = self::LANG_NORWEGIAN;
            else $this->lang = self::LANG_ENGLISH;
        }

        $filename = "{$this->type}_vipps_{$this->shape}_{$this->size}_{$this->lang}.svg";
        if ($a == null) $a = '';
        else $a = ' ' . $a;
        if ($img == null) $img = '';
        else $img = ' ' . $img;
        if(isset($this->return_url)) $href = $this->href . '?r=' . $this->return_url;
        else $href = $this->href;
        return "<a href=\"{$href}\"{$a}><img src=\"/vipps/asset/button/{$filename}\"{$img}></a>";
    }

    public function login(): self
    {
        $this->type = self::TYPE_LOGIN;
        $this->href = Craft::$app->request->getHostInfo().'/vipps/redirect/login';
        return $this;
    }

    public function continue(): self
    {
        $this->type = self::TYPE_CONTINUE;
        $this->href = Craft::$app->request->getHostInfo().'/vipps/redirect/continue';
        return $this;
    }

    public function register(): self
    {
        $this->type = self::TYPE_REGISTER;
        return $this;
    }

    public function large(): self
    {
        $this->size = self::SIZE_LARGE;
        return $this;
    }

    public function small(): self
    {
        $this->size = self::SIZE_SMALL;
        return $this;
    }

    public function en(): self
    {
        $this->lang = self::LANG_ENGLISH;
        return $this;
    }

    public function no(): self
    {
        $this->lang = self::LANG_NORWEGIAN;
        return $this;
    }

    public function english(): self
    {
        return $this->en();
    }

    public function norwegian(): self
    {
        return $this->no();
    }

    public function rect(): self
    {
        $this->shape = self::SHAPE_RECTANGLE;
        return $this;
    }

    public function rectangle(): self
    {
        return $this->rect();
    }

    public function pill(): self
    {
        $this->shape = self::SHAPE_PILL;
        return $this;
    }

    public function returnUrl($url): self
    {
        $this->return_url = StringHelper::base64UrlEncode($url);
        return $this;
    }

    // Protected Methods
    // =========================================================================



    // Private Methods
    // =========================================================================
}