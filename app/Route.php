<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Uuid;

class Route extends Model
{
    //
    const IN_PROGRESS = 0;
    const SUCCESS = 1;
    const FAILURE = 2;

    protected $fillable = ['total_distance', 'total_time','error','status'];

	/**
	 * Indicates if the IDs are auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;


    /**
     * Return list of status codes and labels

     * @return array
     */
    public static function listStatus()
    {
        return [
            self::IN_PROGRESS   => 'in progress',
            self::SUCCESS       => 'success',
            self::FAILURE       => 'failure'
        ];
    }


    /**
     * Returns label of actual status

     * @param string
     */
    public function statusLabel()
    {
        $list = self::listStatus();
        return isset($list[$this->status]) 
            ? $list[$this->status] 
            : $this->status;
    }


    public function start()
    {
        return $this->hasOne('App\RouteStart', 'route_id');
    }

    public function dropoffs()
    {
        return $this->hasMany('App\RouteDropoff', 'route_id')->orderBy('step');
    }

    /**
     * Boot function from laravel.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = Uuid::generate()->string;
        });
    }
}
