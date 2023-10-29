<?php
declare(strict_types=1);

namespace Dux\Database;

use Dux\App;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

class Model extends \Illuminate\Database\Eloquent\Model
{

    public function __construct(array $attributes = [])
    {
        App::db()->getConnection();
        $this->setConnection('default');
        parent::__construct($attributes);
    }

    protected $fillable = [];
    protected $guarded = [];

    public function migration(Blueprint $table)
    {
    }

    public function migrationAfter(Connection $db)
    {
    }

    public function seed(Connection $db)
    {
    }

}