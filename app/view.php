<?php

class TwigView extends \flight\template\View {
    protected $loader;
    protected $environment;

    private $template;

    public function __construct($path, $extension) {
        parent::__construct();

        $this->extension = $extension;

        $this->loader = new \Twig\Loader\FilesystemLoader($path);
        $this->environment = new \Twig\Environment($this->loader);
    }

    public function render($file, $data = NULL) {
        $this->template = $this->getTemplate($file);

        if (is_array($data)) {
            $this->vars = array_merge($this->vars, $data);
        }

        extract($this->vars);

        echo $this->environment->render($this->template, $this->vars);
    }
}