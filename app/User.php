<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

use Carbon\Carbon;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'fname', 'lname', 'dob' , 'email', 'password', 'username',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $dates = [
        'email_confirmed_at', 'last_seen', 'closed_at', 'suspended_from', 'suspended_until', 'social_level_attained_at', 'verified_at',
    ];

    public function is_admin(){
        return $this->usertype == 'ADMIN' && $this->is_admin ? true : false;
    }

    public function is_user(){
        return $this->usertype == 'USER' ? true : false;
    }

    public function is_moderator(){
        return $this->moderator ? true : false;
    }

    public function is_suspended(){
        return $this->suspended ? true : false;
    }

    public function is_closed(){
        return $this->closed ? true : false;
    }

    public function is_active(){
        return $this->active ? true : false;
    }
    
    public function is_email_verified(){
        return $this->email_verified ? true : false;
    }

    public function profile_picture(){
        return profile_picture($this);
    }

    public function image(){
        return profile_picture($this);
    }

    public function badge(){
        return social_badge($this->social_level);
    }

    public function social_status(){
        return ucfirst(strtolower($this->social_level)) . ' Member';
    }

    public function profile_thumbnail(){
        return profile_thumbnail($this);
    }

    public function thumbnail(){
        return profile_thumbnail($this);
    }

    public function profile(){
        return $this->hasOne('App\Profile', 'user_id');
    }

    public function simba_coin_logs(){
        return $this->hasMany('App\SimbaCoinLog', 'user_id');
    }

    public function can_be_moderator(){
        return $this->accumulated_coins >= config('coins.social_levels.hodari') ? true : false;
    }

    public function message_notifications(){
        return $this->hasMany('App\MessageNotification', 'to_id');
    }

    public function notifications(){
        return $this->hasMany('App\Notification', 'to_id');
    }

    public function check_social_level(){
        $changes          = false;
        $previous_balance = 0;
        $current_balance  = 0;
        $amount           = 0;

        if($this->accumulated_coins < config('coins.social_levels.uungano') && $this->social_level != 'MWANZO'){
            $this->social_level     = 'MWANZO';
            $new_level              = $this->social_level;
            $changes                = true;
        }

        elseif($this->accumulated_coins >= config('coins.social_levels.uungano') && $this->accumulated_coins < config('coins.social_levels.stahimili') && $this->social_level != 'UUNGANO'){
            
            $this->social_level      = 'UUNGANO';
            
            $previous_balance        = $this->coins;

            $this->coins             += config('coins.earn.mwanzo_uungano');
            $this->accumulated_coins += config('coins.earn.mwanzo_uungano');

            $new_level                = $this->social_level;
            $amount                   = config('coins.earn.mwanzo_uungano');

            $current_balance         = $this->coins;
            
            $changes                  = true;

        }

        elseif($this->accumulated_coins >= config('coins.social_levels.stahimili') && $this->accumulated_coins < config('coins.social_levels.shupavu') && $this->social_level != 'STAHIMILI'){
            
            $this->social_level       = 'STAHIMILI';
            
            $previous_balance         = $this->coins;

            $this->coins             += config('coins.earn.uungano_stahimili');
            $this->accumulated_coins += config('coins.earn.uungano_stahimili');

            $new_level                = $this->social_level;
            $amount                   = config('coins.earn.uungano_stahimili');

            $current_balance          = $this->coins;

            $changes                  = true;

        }

        elseif($this->accumulated_coins >= config('coins.social_levels.shupavu') && $this->accumulated_coins < config('coins.social_levels.hodari') && $this->social_level != 'SHUPAVU'){
            
            $this->social_level       = 'SHUPAVU';
            
            $previous_balance         = $this->coins;
            
            $this->coins             += config('coins.earn.stahimili_shupavu');
            $this->accumulated_coins += config('coins.earn.stahimili_shupavu');

            $current_balance          = $this->coins;

            $changes                  = true;

            $new_level                = $this->social_level;
            $amount                   = config('coins.earn.stahimili_shupavu');
        }

        elseif($this->accumulated_coins >= config('coins.social_levels.hodari') && $this->accumulated_coins < config('coins.social_levels.shujaa') && $this->social_level != 'HODARI'){
            
            $this->social_level       = 'HODARI';
            
            $previous_balance         = $this->coins;

            $this->coins             += config('coins.earn.shupavu_hodari');
            $this->accumulated_coins += config('coins.earn.shupavu_hodari');

            $current_balance          = $this->coins;

            $new_level                = $this->social_level;
            $amount                   = config('coins.earn.shupavu_hodari');

            $changes                  = true;
        }

        elseif($this->accumulated_coins >= config('coins.social_levels.shujaa') && $this->accumulated_coins < config('coins.social_levels.bingwa') && $this->social_level != 'SHUJAA'){
            
            $this->social_level       = 'SHUJAA';
            
            $previous_balance         = $this->coins;

            $this->coins             += config('coins.earn.hodari_shujaa');
            $this->accumulated_coins += config('coins.earn.hodari_shujaa');

            $current_balance          = $this->coins;

            $new_level                = $this->social_level;
            $amount                   = config('coins.earn.hodari_shujaa');

            $changes                  = true;
        }

        elseif($this->accumulated_coins >= config('coins.social_levels.bingwa') && $this->social_level != 'BINGWA'){
            
            $this->social_level       = 'BINGWA';

            $previous_balance         = $this->coins;

            $this->coins             += config('coins.earn.shujaa_bingwa');
            $this->accumulated_coins += config('coins.earn.shujaa_bingwa');

            $current_balance          = $this->coins;

            $new_level                = $this->social_level;
            $amount                   = config('coins.earn.shujaa_bingwa');

            $changes                  = true;

        }

        if($changes){
            $message = 'You have attained ' . strtolower($new_level) . ' social level.';

            if($amount > 0){
                $settings = Setting::get();

                $set = new \stdClass();

                foreach ($settings as $setting) {
                    $set->{$setting->name} = $setting;
                }

                $set->available_balance->value       += $amount;
                $set->available_balance->update();

                $set->coins_in_circulation->value    += $amount;
                $set->coins_in_circulation->update();

                $simba_coin_log                        = new \App\SimbaCoinLog;
                $simba_coin_log->user_id               = $this->id;
                $simba_coin_log->message               = 'Simba Coins earned for advancing to ' . strtolower($new_level) . ' social level.';
                $simba_coin_log->type                  = 'credit';
                $simba_coin_log->coins                 = $amount;
                $simba_coin_log->previous_balance      = $previous_balance;
                $simba_coin_log->current_balance      += $current_balance;
                $simba_coin_log->save();

                $message .= ' You have been awarded ' . $amount . ' Simba Coins';

                $timeline           = new \App\Timeline;
                $timeline->user_id  = $this->id;
                $timeline->model_id = $this->id;
                $timeline->message  = 'Advanced to  ' . strtolower($new_level) . ' social level';
                $timeline->type     = 'social_level.upgraded';
                $timeline->extra    = $new_level;
                $timeline->save();
            }

            $this->social_level_attained_at = Carbon::now();
            $this->update();
            
            //session()->flash('success', $message);
        }
        
    }

    public function reviews(){
        return $this->hasMany('App\UserReview', 'user_id');
    }

    public function photos(){
        return $this->hasMany('App\UserPhoto', 'user_id');
    }

    public function donated_items(){
        return $this->hasMany('App\DonatedItem', 'donor_id');
    }

    public function bought_items(){
        return $this->hasMany('App\DonatedItem', 'buyer_id');
    }

    public function good_deeds(){
        return $this->hasMany('App\GoodDeed', 'user_id');
    }

    public function timeline(){
        return $this->hasMany('App\Timeline', 'user_id');
    }

    public function memberships(){
        return $this->hasMany('App\Membership', 'user_id');
    }

    public function education(){
        return $this->hasMany('App\Education', 'user_id');
    }

    public function work_experience(){
        return $this->hasMany('App\WorkExperience', 'user_id');
    }

    public function skills(){
        return $this->hasMany('App\Skill', 'user_id');
    }

    public function awards(){
        return $this->hasMany('App\Award', 'user_id');
    }

    public function hobbies(){
        return $this->hasMany('App\Hobby', 'user_id');
    }

    public function achievements(){
        return $this->hasMany('App\Achievement', 'user_id');
    }

    public function coin_purchase_history(){
        return $this->hasMany('App\CoinPurchaseHistory', 'user_id');
    }
}