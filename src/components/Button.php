<?php


namespace vippsas\login\components;

use Craft;

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

    /**
     * Button size
     * @var int
     */
    private $size = self::SIZE_LARGE;

    /**
     * Button shape
     * @var string
     */
    private $shape = self::SHAPE_RECTANGLE;

    /**
     * Button language
     * @var string
     */
    private $lang;

    /**
     * Button href
     * @var string
     */
    private $href;

    /**
     * Button type
     * @var string
     */
    private $type = self::TYPE_LOGIN;

    // Public Methods
    // =========================================================================

    /**
     * LogInButton constructor.
     * @param string $href
     */
    public function __construct()
    {
        $this->href = Craft::$app->request->getHostInfo().'/vipps/redirect/login';
    }

    /**
     * Render the button
     * @param string|null $a
     * @param string|null $img
     * @return string
     */
    public function render(string $a = null, string $img = null) : string
    {
        \Craft::$app->user->setReturnUrl(Craft::$app->request->getUrl());

        // If the button language is not given, try to set the language based on the site language
        if($this->lang == null)
        {
            if(in_array(Craft::$app->sites->currentSite->language, ['nb', 'nn', 'nb-NO', 'nn-NO'])) $this->lang = self::LANG_NORWEGIAN;
            else $this->lang = self::LANG_ENGLISH;
        }

        $filename = "{$this->type}_vipps_{$this->shape}_{$this->size}_{$this->lang}.svg";
        if ($a == null) $a = '';
        else $a = ' ' . $a;
        if ($img == null) $img = '';
        else $img = ' ' . $img;
        return "<a href=\"{$this->href}\"{$a}><img src=\"/vipps/asset/button/{$filename}\"{$img}></a>";
    }

    /**
     * Make the button "Log in with vipps"
     * @return $this
     */
    public function login()
    {
        $this->type = self::TYPE_LOGIN;
        $this->href = Craft::$app->request->getHostInfo().'/vipps/redirect/login';
        return $this;
    }

    /**
     * Make the button "Continue with vipps"
     * @return $this
     */
    public function continue()
    {
        $this->type = self::TYPE_CONTINUE;
        $this->href = Craft::$app->request->getHostInfo().'/vipps/redirect/continue';
        return $this;
    }

    /**
     * Make the button "Register with vipps"
     * @return $this
     */
    public function register()
    {
        $this->type = self::TYPE_REGISTER;
        return $this;
    }

    /**
     * Make the button large
     * @return $this
     */
    public function large()
    {
        $this->size = self::SIZE_LARGE;
        return $this;
    }

    /**
     * Make the button large
     * @return $this
     */
    public function small()
    {
        $this->size = self::SIZE_SMALL;
        return $this;
    }

    /**
     * Make the button text english
     * @return $this
     */
    public function en()
    {
        $this->lang = self::LANG_ENGLISH;
        return $this;
    }

    /**
     * Make the button text norwegian
     * @return $this
     */
    public function no()
    {
        $this->lang = self::LANG_NORWEGIAN;
        return $this;
    }

    /**
     * Make the button text english
     * @return $this
     */
    public function english()
    {
        return $this->en();
    }

    /**
     * Make the button text norwegian
     * @return $this
     */
    public function norwegian()
    {
        return $this->no();
    }

    /**
     * Make the button rectangle shaped
     * @return $this
     */
    public function rect()
    {
        $this->shape = self::SHAPE_RECTANGLE;
        return $this;
    }

    /**
     * Make the button rectangle shaped
     * @return $this
     */
    public function rectangle()
    {
        return $this->rect();
    }

    /**
     * Make the button pill shaped
     * @return $this
     */
    public function pill()
    {
        $this->shape = self::SHAPE_PILL;
        return $this;
    }

    // Protected Methods
    // =========================================================================



    // Private Methods
    // =========================================================================
}