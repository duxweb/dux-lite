<?php

namespace Core\Model;


class Trans
{

  private $locale;
  private $translations;
  private $parent;
  private $fallbackLocale;

  public function __construct($translations, $locale, $parent, $fallbackLocale = 'en-US')
  {
    $this->translations = $translations ?? [];
    $this->locale = $locale;
    $this->parent = $parent;
    $this->fallbackLocale = $fallbackLocale;
  }

  public function __get($name)
  {
    $value = $this->translations[$this->locale][$name] ?? null;

    if ($value === null && $this->locale !== $this->fallbackLocale) {
      $value = $this->translations[$this->fallbackLocale][$name] ?? null;
    }

    return $value;
  }

  public function __set($name, $value)
  {
    if (!isset($this->translations[$this->locale])) {
      $this->translations[$this->locale] = [];
    }
    $this->translations[$this->locale][$name] = $value;
    $this->parent->translations = $this->translations;
  }
}
