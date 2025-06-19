<?php

namespace Core\Model;

use Illuminate\Database\Schema\Blueprint;

class TransSet
{

  public static function columns(Blueprint $table)
  {
    $table->json('translations')->nullable()->comment('语言翻译');
  }
}
