<?php

namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Model;

//class Mbfcustomer extends Model
class ResUser extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'remember_token','username','refferal_code','phone_number','user_image'
        ,'country','uid','is_pro','subscription_status','subscription_type','subscription_expiry',
        'revenuecat_customer_id','revenuecat_app_user_id','revenuecat_product_id',
        'revenuecat_entitlement_id','subscription_store','subscription_last_event',
        'subscription_updated_at'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        //'password', 'remember_token',
        'password',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_pro' => 'boolean',
        'subscription_expiry' => 'datetime',
        'subscription_updated_at' => 'datetime',
    ];


    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
	public $table = 'res_users';
	//public $table = 'res_users';
	protected $primaryKey = 'id';
	//protected $primaryKey = 'id';
	
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function referral()
    {
        return $this->hasOne('App\Models\Referral');
    }
    
    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->uid) {
                $user->uid = self::generateUniqueUid();
            }
        });
    }

    protected static function generateUniqueUid()
    {
        do {
            $uid = random_int(10000000, 99999999);
        } while (self::where('uid', $uid)->exists());

        return $uid;
    }
}
