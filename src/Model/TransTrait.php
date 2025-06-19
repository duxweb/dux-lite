<?php

namespace Core\Model;

trait TransTrait
{

  /**
   * 设置翻译字段
   * @param mixed $value 
   * @return void 
   */
  public function setTranslationsAttribute($value)
  {
    $this->attributes['translations'] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  }

  /**
   * 获取翻译字段
   * @param mixed $value 
   * @return array 
   */
  public function getTranslationsAttribute($value)
  {
    return json_decode($value, true) ?? [];
  }

  /**
   * 获取翻译对象
   * @param string $locale 
   * @param string|null $fallbackLocale 
   * @return Trans 
   */
  public function translate(string $locale, ?string $fallbackLocale = null): Trans
  {
    return new Trans($this->translations, $locale, $this, $fallbackLocale);
  }
}
