<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\{User, DonatedItem, DonatedItemImage, Profile, Timeline, UserReview, SimbaCoinLog, Notification, GoodDeed, GoodDeedImage, Membership, Education, WorkExperience, Skill, Award, Hobby, Achievement, Escrow, CoinPurchaseHistory, Conversation, Message, MessageNotification};

use Image, Auth, Session;

use Carbon\Carbon;

class UserController extends Controller
{
    public function __construct(){
    	$this->middleware('auth');
        $this->middleware('is_user');
    	$this->middleware('not_closed');
        $this->middleware('check_coins');
        $this->initialize();
    }

    public function showDashboard(){
    	$user = auth()->user();
        

        return view('pages.user.index', [
    		'title' => 'User Dashboard',
    		'nav'	=> 'user.dashboard',
    	]);
    }

    public function showBalance(){
        $user = auth()->user();

        $coin_request = $user->coin_purchase_history()->where('approved', 0)->where('disapproved', 0)->first();
        
        return view('pages.user.user-balance', [
            'title'         => 'Account Balance',
            'nav'           => 'user.account-balance',
            'user'          => $user,
            'coin_request'  => $coin_request,
            'settings'      => $this->settings,
        ]);
    }

    public function showConversations(){
        $user = auth()->user();

        $conversations = Conversation::where('from_id', $user->id)->orWhere('to_id', $user->id)->orderBy('updated_at', 'DESC')->get();

        $message_notifications = $user->message_notifications()->where('read', 0)->get();

        if(count($message_notifications)){
            foreach ($message_notifications as $r) {
                $r->read = 1;
                $r->read_at = $this->date;
                $r->update();
            }
        }
        
        return view('pages.user.conversations', [
            'title'         => 'Conversations',
            'nav'           => 'user.account-balance',
            'user'          => $user,
            'conversations' => $conversations,
        ]);
    }

    public function showConversation($id){
        $user   = auth()->user();
        $to     = false;

        $conversation = Conversation::findOrFail($id);

        $intended = $conversation->from_id || $conversation->to_id == $user->id ? true : false;

        if(!$intended){
            session()->flash('error', 'Forbidden');
            return redirect()->back();
        }

        $conversations = Conversation::where('from_id', $user->id)->orWhere('to_id', $user->id)->orderBy('updated_at', 'DESC')->get();

        $message_notifications = $conversation->notifications()->where('read', 0)->where('to_id', $user->id)->get();

        if(count($message_notifications)){
            foreach ($message_notifications as $r) {
                $r->read = 1;
                $r->read_at = $this->date;
                $r->update();
            }
        }

        $notifications = $conversation->notifications()->where('read', 0)->where('to_id', $user->id)->get();

        if(count($notifications)){
            foreach ($notifications as $notification) {
                $notification->read = 1;
                $notification->read_at = $this->date;
                $notification->update();
            }
        }

        $messages = $conversation->messages()->orderBy('created_at', 'ASC')->get();

        if($conversation->from_id == $user->id){
            $to = $conversation->to;
        }else{
            $to = $conversation->from;
        }
        
        return view('pages.user.conversation', [
            'title'                 => 'Conversation',
            'nav'                   => 'user.account-balance',
            'user'                  => $user,
            'conversations'         => $conversations,
            'current_conversation'  => $conversation,
            'messages'              => $messages,
            'support_message'       => $conversation->support,
            'to'                    => $to,
        ]);
    }

    public function getAjaxConversation($id){
        $user           = auth()->user();
        $conversation   = Conversation::find($id);

        if(!$conversation){
            return response()->json(['status' => 404, 'message' => 'Conversation not found']);
        }

        $intended = $conversation->from_id || $conversation->to_id == $user->id ? true : false;

        if(!$intended){
            return response()->json(['status' => 403, 'message' => 'Forbidden']);
        }

        $messages = $conversation->messages()->where('read', 0)->where('to_id', $user->id)->get();
        $notifications = $conversation->notifications()->where('read', 0)->where('to_id', $user->id)->get();

        if(count($notifications)){
            foreach ($notifications as $notification) {
                $notification->read = 1;
                $notification->read_at = $this->date;
                $notification->update();
            }
        }
        
        $count = count($messages);

        if($count){
            foreach ($messages as $message) {
                $message->read = 1;
                $message->read_at = $this->date;
                $message->update();
            }
        }

        $messages = [];

        foreach ($conversation->messages()->orderBy('created_at', 'ASC')->get() as $message) {
            if($message->from_admin){
                $from = 'Admin';
            }else{
                if($message->from_id){
                    if($message->from_id == $user->id){
                        $from = 'Me';
                    }else{
                        $from = $message->sender->name;
                    }
                }
            }
            

            $messages[] = [
                'from'      => $from,
                'mine'      => $message->from_id == $user->id ? '1' : '0',
                'message'   => $message->message,
                'time'      => $message->created_at->diffForHumans(),
            ];
        }

        $response = [
            'status'    => 200,
            'message'   => 'Success',
            'messages'  =>  $messages,
            'count'     =>  $count,
        ];

        return response()->json($response);
    }

    public function newMessage(Request $request, $username){
        $support    = $request->has('support') ? 1 : 0;
        $sender     = auth()->user();

        if(!$support){
            $recepient  = User::where('username', $username)->firstOrFail();
            $user       = $sender;

            if($recepient->id == $sender->id){
                if($request->ajax()){
                    return response()->json(['status' => 403, 'message' => 'Sorry, you cant message yourself']);
                }

                session()->flash('error', 'Sorry, you cant message yourself');
                return redirect()->back();
            }

        
            $from_conversation = Conversation::where('from_id', $sender->id)->where('to_id', $recepient->id)->first();
            $to_conversation =   Conversation::where('from_id', $recepient->id)->where('to_id', $sender->id)->first();
            
            if($from_conversation){
                $conversation = $from_conversation;
            }elseif($to_conversation){
                $conversation = $to_conversation;
            }else{
                $conversation = new Conversation;
                $conversation->to_id = $recepient->id;
                $conversation->from_id = $sender->id;
                $conversation->save();
            }

        }else{
            $from_conversation  = Conversation::where('from_id', $sender->id)->where('support', '1')->first();
            $to_conversation    = Conversation::where('to_id', $sender->id)->where('support', '1')->first();
            
            if($from_conversation){
                $conversation = $from_conversation;
            }elseif($to_conversation){
                $conversation = $to_conversation;
            }else{
                $conversation = new Conversation;
                $conversation->to_id = null;
                $conversation->from_id = $sender->id;
                $conversation->support = 1;
                $conversation->save();
            }
        }

        if($request->ajax()){
            return response()->json(['status' => 200, 'message' => 'Message Sent']);
        }

        return redirect()->route('user.conversation', ['id' => $conversation->id]);
    }

    public function postMessage(Request $request, $id){
        $this->validate($request, [
            'message' => 'required',
        ]);

        $user               = auth()->user();
        $recepient          = null;

        $conversation = Conversation::findOrFail($id);

        $intended = $conversation->from_id || $conversation->to_id == $user->id ? true : false;

        if(!$intended){
            if($request->ajax()){
                return response()->json(['status' => 403, 'message' => 'Forbidden']);
            }

            session()->flash('error', 'Forbidden');
            return redirect()->back();
        }

        if(!$conversation->support){
            if($conversation->from_id == $user->id){
                $recepient = $conversation->to;
            }else{
                $recepient = $conversation->from;
            }
        }

        $sender     = $user;
        $user       = $sender;

        if(!$conversation->support && !$recepient){
            $message = 'Recepient not found';

            if($request->ajax()){
                $response = ['status' => 404, 'message' => $message];
                return response()->json($response);
            }
            session()->flash('error', $message);
            return redirect()->back();
        }

        if(!is_null($recepient)){
            if($recepient->id == $sender->id){

                $message = 'Sorry, you cant message yourself';

                if($request->ajax()){
                    $response = ['status' => 403, 'message' => $message];
                    return response()->json($response);
                }

                session()->flash('error', $message);
                
                return redirect()->back();
            }
        }

        $message                    = new Message;
        $message->from_id           = $user->id;
        $message->to_id             = !is_null($recepient) ? $recepient->id : null;
        $message->conversation_id   = $conversation->id;
        $message->message           = ucfirst($request->message);
        $message->support           = $conversation->support;
        $message->save();

        $message_notification                       = new MessageNotification;
        $message_notification->from_id              = $user->id;
        $message_notification->to_id                = !is_null($recepient) ? $recepient->id : null;
        $message_notification->conversation_id      = $conversation->id;
        $message_notification->message_id           = $message->id;
        $message_notification->support              = $conversation->support;
        $message_notification->save();

        $conversation->updated_at = $this->date;
        $conversation->update();

        $message = 'Message sent';

        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);

        return redirect()->back();
    }

    public function showNotifications(){
        $user = auth()->user();

        $notifications = $user->notifications()->orderBy('created_at', 'DESC')->paginate(20);

        return view('pages.user.notifications', [
            'title'         => 'Notifications',
            'nav'           => 'user.notifications',
            'user'          => $user,
            'notifications' => $notifications,
        ]);
    }

    public function postDonateItem(Request $request){

        $this->validate($request, [
            'name'              => 'required|max:255',
            'type'              => 'required|max:255',
            'condition'         => 'required|max:255',
            'category_id'       => 'required|numeric',
            'description'       => 'required',
        ]);

        if($request->hasFile('images')){
            try{
                $this->validate($request,[
                    'images.*' => 'mimes:jpg,jpeg,png,bmp|min:0.001|max:40960',
                ]);
            }catch(\Exception $e){
                session()->flash('error', 'Image Upload failed. Reason: '. $e->getMessage());
                
                return redirect()->back();
            }
        }

        $user = auth()->user();

        $donated_item                   = new DonatedItem;
        $donated_item->name             = $request->name;
        $donated_item->slug             = str_slug($request->name . '-' . rand(1,1000000));
        $donated_item->type             = $request->type;
        $donated_item->condition        = $request->condition;
        $donated_item->category_id      = $request->category_id;
        $donated_item->description      = $request->description;
        $donated_item->donor_id         = $user->id;
        $donated_item->price            = config('coins.donated_item.price');
        $donated_item->save();

        if($request->hasFile('images')){
            $images = $request->file('images');

            foreach ($images as $image) {
                if($image->isValid()){
                    try{

                        $name   = time(). rand(1,1000000) . '.' . $image->getClientOriginalExtension();
                        
                        $image_path         = $this->image_path . '/donated_items/images/' . $name;
                        $banner_path        = $this->image_path . '/donated_items/banners/'. $name;
                        $thumbnail_path     = $this->image_path . '/donated_items/thumbnails/' . $name;
                        $slide_path         = $this->image_path . '/donated_items/slides/' . $name;
                    
                        Image::make($image)->orientate()->resize(800,null, function($constraint){
                            return $constraint->aspectRatio();
                        })->save($image_path);

                        Image::make($image)->orientate()->fit(440,586)->save($banner_path);

                        Image::make($image)->orientate()->fit(769,433)->save($slide_path);

                        Image::make($image)->orientate()->resize(200,null, function($constraint){
                            return $constraint->aspectRatio();
                        })->save($thumbnail_path);

                        $donated_item_image                     = new DonatedItemImage;
                        $donated_item_image->image              = $name;
                        $donated_item_image->banner             = $name;
                        $donated_item_image->thumbnail          = $name;
                        $donated_item_image->slide              = $name;
                        $donated_item_image->donated_item_id    = $donated_item->id;
                        $donated_item_image->user_id            = $user->id;
                        $donated_item_image->save();

                    } catch(\Exception $e){
                        session()->flash('error', 'Image Upload failed. Reason: '. $e->getMessage());
                    }
                }
            }
        }

        $timeline           = new Timeline;
        $timeline->user_id  = $user->id;
        $timeline->model_id = $donated_item->id;
        $timeline->message  = 'Donated ' . $donated_item->name . ' to the Community';
        $timeline->type     = 'item.donated';
        $timeline->save();


        session()->flash('success', 'Item donated to the community');

        return redirect()->route('donated-item.show', ['slug' => $donated_item->slug]);
    }

    public function purchaseDonatedItem($slug){
        $donated_item   = DonatedItem::where('slug', $slug)->firstOrFail();
        $user           = auth()->user();

        if($donated_item->donor_id == $user->id){
            session()->flash('error', 'You cannot purchase an item you donated');
            return redirect()->back();
        }

        if($donated_item->bought){
            session()->flash('error', 'Sorry, the item has been bought');
            return redirect()->back();
        }

        if($user->coins < $donated_item->price){
            session()->flash('error', 'You don not have sufficient simba coins to purchase this item');
            return redirect()->back();
        }

        $user->coins -= $donated_item->price;
        $user->update();

        $escrow                     = new Escrow;
        $escrow->user_id            = $user->id;
        $escrow->donated_item_id    = $donated_item->id;
        $escrow->amount             = $donated_item->price;
        $escrow->save();

        $simba_coin_log                        = new SimbaCoinLog;
        $simba_coin_log->user_id               = $user->id;
        $simba_coin_log->message               = 'Payment for Donated item purchase. DESC: ' . $donated_item->name;
        $simba_coin_log->type                  = 'debit';
        $simba_coin_log->coins                 = $donated_item->price;
        $simba_coin_log->previous_balance      = $user->coins + $donated_item->price ;
        $simba_coin_log->current_balance       = $user->coins;
        $simba_coin_log->save();

        $donated_item->bought       = 1;
        $donated_item->bought_at    = $this->date;
        $donated_item->buyer_id     = $user->id;
        $donated_item->escrow_id    = $escrow->id;
        $donated_item->update();

        session()->flash('success', 'Item bought, the admin will contact you with the details on how to collect your item(s)');

        return redirect()->back();
    }

    public function postGoodDeed(Request $request){

        $this->validate($request, [
            'name'          => 'required|max:255',
            'location'      => 'required|max:255',
            'description'   => 'required|max:800',
            'contacts'      => 'max:800',
            
        ]);

        if($request->hasFile('images')){
            try{
                $this->validate($request,[
                    'images.*' => 'mimes:jpg,jpeg,png,bmp|min:0.001|max:40960',
                ]);
            }catch(\Exception $e){
                session()->flash('error', 'Image Upload failed. Reason: '. $e->getMessage());
                
                return redirect()->back();
            }
        }

        $user = auth()->user();
        

        $good_deed                  = new GoodDeed;
        $good_deed->name            = $request->name;
        $good_deed->slug            = str_slug($request->name . '-' . rand(1,1000000));
        $good_deed->location        = $request->location;
        $good_deed->performed_at    = $request->performed_at;
        $good_deed->description     = $request->description;
        $good_deed->contacts        = $request->contacts;
        $good_deed->user_id         = $user->id;
        
        $good_deed->save();

        if($request->hasFile('images')){
            $images = $request->file('images');

            foreach ($images as $image) {
                if($image->isValid()){
                    try{

                        $name   = time(). rand(1,1000000) . '.' . $image->getClientOriginalExtension();
                        
                        $image_path         = $this->image_path . '/good_deeds/images/' . $name;
                        $thumbnail_path     = $this->image_path . '/good_deeds/thumbnails/' . $name;
                        
                        Image::make($image)->orientate()->resize(1024,null, function($constraint){
                            return $constraint->aspectRatio();
                        })->save($image_path); 

                        Image::make($image)->orientate()->fit(400,260)->save($thumbnail_path); 

                        $good_deed_image                = new GoodDeedImage;
                        $good_deed_image->image         = $name;
                        $good_deed_image->good_deed_id  = $good_deed->id;
                        $good_deed_image->user_id       = $user->id;
                        
                        $good_deed_image->save();

                    } catch(\Exception $e){
                        session()->flash('error', 'Image Upload failed. Reason: '. $e->getMessage());
                    }
                }
            }
        }


        session()->flash('success', 'Good deed reported, please wait for approval by the admin');

        return redirect()->route('good-deed.show', ['slug' => $good_deed->slug]);
    }

    public function postUserReview(Request $request, $username){
        $this->validate($request,[
            'rating'    => 'required|numeric|min:1|max:5',
            'message'   => 'required|max:800',
        ]);

        $user = User::where('username', $username)->firstOrFail();
        $auth = auth()->user();

        if($user->id == $auth->id){
            session()->flash('error', 'You Cannot rate yourself');
            return redirect()->back();
        }

        $reviewed = $user->reviews()->where('rater_id', $auth->id)->first();

        if($reviewed){
            session()->flash('error', 'You have already reviewed this user');
            return redirect()->back();
        }

        $review             = new UserReview;
        $review->user_id    = $user->id;
        $review->rater_id   = $auth->id;
        $review->rating     = $request->rating;
        $review->message    = $request->message;
        $review->save();

        $user->rating       += $request->rating;
        $user->reviews      += 1;
        $user->update();

        $previous_balance   = $auth->coins;

        $auth->coins                += config('coins.earn.rating_member');
        $auth->accumulated_coins    += config('coins.earn.rating_member');
        $auth->update();

        $this->settings->available_balance->value       += config('coins.earn.rating_member');
        $this->settings->available_balance->update();

        $this->settings->coins_in_circulation->value    += config('coins.earn.rating_member');
        $this->settings->coins_in_circulation->update();

        $simba_coin_log                        = new SimbaCoinLog;
        $simba_coin_log->user_id               = $auth->id;
        $simba_coin_log->message               = 'Simba Coins earned for reviewing ' . $user->name;
        $simba_coin_log->type                  = 'credit';
        $simba_coin_log->coins                 = config('coins.earn.rating_member');;
        $simba_coin_log->previous_balance      = $previous_balance;
        $simba_coin_log->current_balance      += $auth->coins;
        $simba_coin_log->save();

        $notification                       = new Notification;
        $notification->from_id              = $auth->id;
        $notification->to_id                = $user->id;
        $notification->message              = $auth->name . ' Reviewed your profile.';
        $notification->notification_type    = 'user.reviewed';
        $notification->model_id             = $user->id;
        $notification->save();

        session()->flash('success', 'User reviewed');

        return redirect()->back();
    }

    public function showMyProfile(){
        $user = auth()->user();

        return view('pages.user.my-profile', [
            'title'     => 'Profile',
            'nav'       => 'user.profile',
            'user'      => $user,
        ]);
    }

    public function showSettings(){
        $user = auth()->user();

        return view('pages.user.user-settings', [
            'title'     => 'Settings',
            'nav'       => 'user.settings',
            'user'      => $user,
        ]);
    }

    public function addMembership(Request $request){
        
        $this->validate($request, [
            'name'  => 'max:255|required',
        ]);

        $membership = new Membership;
        $membership->name = $request->name;
        $membership->user_id = auth()->user()->id;
        $membership->save();

        $message = 'Membership Added';
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function updateMembership(Request $request, $id){
        $membership = Membership::findOrFail($id);

        $user = auth()->user();

        if($membership->user_id != $user->id){
            session()->flash('error', 'Forbidden');

            return redirect()->back();
        }

        $this->validate($request, [
            'name'  => 'required|max:255',
        ]);

        $membership->name = $request->name;
        $membership->update();

        session()->flash('success', 'Membership Updated');

        return redirect()->back();
    }

    public function deleteMembership(Request $request, $id){
        $membership = Membership::findOrFail($id);

        $user = auth()->user();

        if($membership->user_id != $user->id){
            session()->flash('error', 'Forbidden');

            return redirect()->back();
        }

        $membership->delete();

        session()->flash('success', 'Membership Deleted');

        return redirect()->back();
    }

    public function addAward(Request $request){
        
        $this->validate($request, [
            'name'  => 'max:255|required',
            'year'  => 'min:1900|max:' . date('Y') . '|required|numeric',
        ]);

        $award = new Award;
        $award->name = $request->name;
        $award->year = $request->year;
        $award->user_id = auth()->user()->id;
        $award->save();

        $message = 'Award Added';
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function updateAward(Request $request, $id){
        $award = Award::findOrFail($id);

        $user = auth()->user();

        if($award->user_id != $user->id){
            session()->flash('error', 'Forbidden');

            return redirect()->back();
        }

        $this->validate($request, [
            'name'  => 'required|max:255',
            'year'  => 'required|numeric|min:1900|max:' . date('Y'),
        ]);

        $award->name = $request->name;
        $award->year = $request->year;
        $award->update();

        session()->flash('success', 'Award Updated');

        return redirect()->back();
    }

    public function deleteAward(Request $request, $id){
        $award = Award::findOrFail($id);

        $user = auth()->user();

        if($award->user_id != $user->id){
            session()->flash('error', 'Forbidden');

            return redirect()->back();
        }

        $award->delete();

        session()->flash('success', 'Award Deleted');

        return redirect()->back();
    }

    public function addHobby(Request $request){
        
        $this->validate($request, [
            'name'  => 'max:255|required',
        ]);

        $hobby = new Hobby;
        $hobby->name = $request->name;
        $hobby->user_id = auth()->user()->id;
        $hobby->save();

        $message = 'Hobby Added';
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function updateHobby(Request $request, $id){
        $hobby = Hobby::findOrFail($id);

        $user = auth()->user();

        if($hobby->user_id != $user->id){
            session()->flash('error', 'Forbidden');

            return redirect()->back();
        }

        $this->validate($request, [
            'name'  => 'required|max:255',
        ]);

        $hobby->name = $request->name;
        $hobby->update();

        session()->flash('success', 'Hobby Updated');

        return redirect()->back();
    }

    public function deleteHobby(Request $request, $id){
        $hobby = Hobby::findOrFail($id);

        $user = auth()->user();

        if($hobby->user_id != $user->id){
            session()->flash('error', 'Forbidden');

            return redirect()->back();
        }

        $hobby->delete();

        session()->flash('success', 'Hobby Deleted');

        return redirect()->back();
    }

    public function addAchievement(Request $request){
        
        $this->validate($request, [
            'name'  => 'max:255|required',
        ]);

        $achievement = new Achievement;
        $achievement->name = $request->name;
        $achievement->user_id = auth()->user()->id;
        $achievement->save();

        $message = 'Achievement Added';
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function updateAchievement(Request $request, $id){
        $achievement = Achievement::findOrFail($id);

        $user = auth()->user();

        if($achievement->user_id != $user->id){
            session()->flash('error', 'Forbidden');

            return redirect()->back();
        }

        $this->validate($request, [
            'name'  => 'required|max:255',
        ]);

        $achievement->name = $request->name;
        $achievement->update();

        session()->flash('success', 'Achievement Updated');

        return redirect()->back();
    }

    public function deleteAchievement(Request $request, $id){
        $achievement = Achievement::findOrFail($id);

        $user = auth()->user();

        if($achievement->user_id != $user->id){
            session()->flash('error', 'Forbidden');

            return redirect()->back();
        }

        $achievement->delete();

        session()->flash('success', 'Achievement Deleted');

        return redirect()->back();
    }

    public function addWorkExperience(Request $request){
        $this->validate($request,[
            'from'              => 'required|max:255',
            'to'                => 'required|max:255',
            'company'           => 'required|max:255',
            'position'          => 'required|max:255',
        ]);

        $work_experience = new WorkExperience;

        $work_experience->fromdate      = $request->from;
        $work_experience->todate        = $request->to;
        $work_experience->company   = $request->company;
        $work_experience->position  = $request->position;
        $work_experience->user_id   = auth()->user()->id;

        $work_experience->save();

        $message = "Work Experience added";
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function updateWorkExperience(Request $request, $id){
        $this->validate($request,[
            'from'              => 'required|max:255',
            'to'                => 'required|max:255',
            'company'           => 'required|max:255',
            'position'          => 'required|max:255',
        ]);

        $work_experience = WorkExperience::findOrFail($id);

        if($work_experience->user_id != auth()->user()->id){
            $message = "Forbidden";
        
            if($request->ajax()){
                $response = ['status' => 403, 'message' => $message];
                return response()->json($response);
            }

            session()->flash('error', $message);
            return redirect()->back();
        }

        $work_experience->fromdate      = $request->from;
        $work_experience->todate        = $request->to;
        $work_experience->company   = $request->company;
        $work_experience->position  = $request->position;

        $work_experience->update();

        $message = "Work Experience Updated";
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function deleteWorkExperience(Request $request, $id){
    
        $work_experience = WorkExperience::findOrFail($id);

        if($work_experience->user_id != auth()->user()->id){
            $message = "Forbidden";
        
            if($request->ajax()){
                $response = ['status' => 403, 'message' => $message];
                return response()->json($response);
            }

            session()->flash('error', $message);
            return redirect()->back();
        }

        $work_experience->delete();

        $message = "Work Experience Removed";
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function addSkill(Request $request){
        $this->validate($request,[
            'skill' => 'required|max:255',
        ]);

        $skill = new Skill;

        $skill->skill   = $request->skill;
        $skill->user_id = auth()->user()->id;

        $skill->save();

        $message = "Skill added";
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function updateSkill(Request $request, $id){
        $this->validate($request,[
            'skill' => 'required|max:255',
        ]);

        $skill = Skill::findOrFail($id);

        if($skill->user_id != auth()->user()->id){
            $message = "Forbidden";
        
            if($request->ajax()){
                $response = ['status' => 403, 'message' => $message];
                return response()->json($response);
            }

            session()->flash('error', $message);
            return redirect()->back();
        }

        $skill->skill = $request->skill;

        $skill->update();

        $message = "Skill Updated";
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function deleteSkill(Request $request, $id){
    
        $skill = Skill::findOrFail($id);

        if($skill->user_id != auth()->user()->id){
            $message = "Forbidden";
        
            if($request->ajax()){
                $response = ['status' => 403, 'message' => $message];
                return response()->json($response);
            }

            session()->flash('error', $message);
            return redirect()->back();
        }

        $skill->delete();

        $message = "Skill Removed";
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function addEducation(Request $request){
        $this->validate($request,[
            'school'            => 'required|max:255',
            'level'             => 'required|max:255',
            'field_of_study'    => 'required|max:255',
            'grade'             => 'max:255',
            'start_year'        => 'required|max:255',
            'end_year'          => 'required|max:255',
        ]);

        $education = new Education;

        $education->school = $request->school;
        $education->level = $request->level;
        $education->field_of_study = $request->field_of_study;
        $education->grade = $request->grade;
        $education->start_year = $request->start_year;
        $education->end_year = $request->end_year;
        $education->user_id = auth()->user()->id;

        $education->save();

        $message = "Education added";
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function updateEducation(Request $request, $id){
        $this->validate($request,[
            'school'            => 'required|max:255',
            'level'             => 'required|max:255',
            'field_of_study'    => 'required|max:255',
            'grade'             => 'max:255',
            'start_year'        => 'required|max:255',
            'end_year'          => 'required|max:255',
        ]);

        $education = Education::findOrFail($id);

        if($education->user_id != auth()->user()->id){
            $message = "Forbidden";
        
            if($request->ajax()){
                $response = ['status' => 403, 'message' => $message];
                return response()->json($response);
            }

            session()->flash('error', $message);
            return redirect()->back();
        }

        $education->school = $request->school;
        $education->level = $request->level;
        $education->field_of_study = $request->field_of_study;
        $education->grade = $request->grade;
        $education->start_year = $request->start_year;
        $education->end_year = $request->end_year;
        $education->user_id = auth()->user()->id;

        $education->update();

        $message = "Education Updated";
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function deleteEducation(Request $request, $id){
    
        $education = Education::findOrFail($id);

        if($education->user_id != auth()->user()->id){
            $message = "Forbidden";
        
            if($request->ajax()){
                $response = ['status' => 403, 'message' => $message];
                return response()->json($response);
            }

            session()->flash('error', $message);
            return redirect()->back();
        }

        $education->delete();

        $message = "Education Removed";
        
        if($request->ajax()){
            $response = ['status' => 200, 'message' => $message];
            return response()->json($response);
        }

        session()->flash('success', $message);
        return redirect()->back();
    }

    public function updateAboutMe(Request $request){
        $this->validate($request, [
            'about_me' => 'required|max:800',
        ]);

        $user               = auth()->user();
        $user->about_me     = $request->about_me;
        $user->update();

        session()->flash('success', 'Details updated');

        return redirect()->back();
    }

    public function updateDonatedItem(Request $request, $slug){
        $this->validate($request, [
            'name'              => 'required|max:255',
            'type'              => 'required|max:255',
            'condition'         => 'required|max:255',
            'category_id'       => 'required|numeric',
            'description'       => 'required',
        ]);

        $donated_item       = DonatedItem::where('slug', $slug)->firstOrFail();

        $user               = auth()->user();
        
        if($user->id != $donated_item->donor_id){
            session()->flash('error', 'Forbidden');
            return redirect()->back();
        }

        if($donated_item->bought){
            session()->flash('error', 'The Item has already been bought, no more ammendment allowed');
            return redirect()->back();
        }
        
        if($donated_item->name != $request->name){
            $donated_item->slug         = str_slug($request->name . '-' . rand(1,1000000));
        }

        $donated_item->name             = $request->name;
        
        $donated_item->type             = $request->type;
        $donated_item->condition        = $request->condition;
        $donated_item->category_id      = $request->category_id;
        $donated_item->description      = $request->description;
        $donated_item->update();

        session()->flash('success', 'Item Updated');

        return redirect()->route('donated-item.show', ['slug' => $donated_item->slug]);
    }

    public function deleteDonatedItem(Request $request, $slug){
        $this->validate($request, [
            'reason' => 'required|max:800',
        ]);

        $item = DonatedItem::where('slug', $slug)->firstOrFail();
        $user = auth()->user();

        if($item->donor_id != $user->id){
            session()->flash('error', 'Forbidden');

            return redirect()->back();
        }

        if($item->bought){
            session()->flash('error', 'The Item has already been bought, you cannot delete it');
            return redirect()->back();
        }

        if(count($item->images)){
            foreach ($item->images as $image) {
                @unlink($this->image_path . '/donated_items/banners/' . $image->image);
                @unlink($this->image_path . '/donated_items/images/' . $image->image);
                @unlink($this->image_path . '/donated_items/slides/' . $image->image);
                @unlink($this->image_path . '/donated_items/thumbnails/' . $image->image);
            }
        }

        $timeline = $user->timeline()->where('type', 'item.donated')->where('model_id', $item->id)->first();

        if($timeline){
            $timeline->delete();
        }

        $item->deleted_by = $user->id;
        $item->deleted_reason = $request->reason;
        $item->update();

        $item->delete();

        session()->flash('success', 'Donated item removed from community shop');

        return redirect()->route('community-shop');
    }

    public function addDonatedItemImage(Request $request, $slug){
        $donated_item = DonatedItem::where('slug', $slug)->firstOrFail();

        $user = auth()->user();

        if($donated_item->donor_id != $user->id){
            session()->flash('error', 'Forbidden');

            return redirect()->back();
        }

        try{
            $this->validate($request,[
                'images.*' => 'mimes:jpg,jpeg,png,bmp|min:0.001|max:40960',
            ]);
        }catch(\Exception $e){
            session()->flash('error', 'Image Upload failed. Reason: '. $e->getMessage());
            
            return redirect()->back();
        }

        if($request->hasFile('images')){
            $images = $request->file('images');

            foreach ($images as $image) {
                if($image->isValid()){
                    try{

                        $name   = time(). rand(1,1000000) . '.' . $image->getClientOriginalExtension();
                        
                        $image_path         = $this->image_path . '/donated_items/images/' . $name;
                        $banner_path        = $this->image_path . '/donated_items/banners/'. $name;
                        $thumbnail_path     = $this->image_path . '/donated_items/thumbnails/' . $name;
                        $slide_path         = $this->image_path . '/donated_items/slides/' . $name;
                    
                        Image::make($image)->orientate()->resize(800,null, function($constraint){
                            return $constraint->aspectRatio();
                        })->save($image_path);

                        Image::make($image)->orientate()->fit(440,586)->save($banner_path);

                        Image::make($image)->orientate()->fit(769,433)->save($slide_path);

                        Image::make($image)->orientate()->resize(200,null, function($constraint){
                            return $constraint->aspectRatio();
                        })->save($thumbnail_path);

                        $donated_item_image                     = new DonatedItemImage;
                        $donated_item_image->image              = $name;
                        $donated_item_image->banner             = $name;
                        $donated_item_image->thumbnail          = $name;
                        $donated_item_image->slide              = $name;
                        $donated_item_image->donated_item_id    = $donated_item->id;
                        $donated_item_image->user_id            = $user->id;
                        $donated_item_image->save();

                    } catch(\Exception $e){
                        session()->flash('error', 'Image Upload failed. Reason: '. $e->getMessage());
                    }
                }
            }
        }

        else{
            session()->flash('error', 'Upload failed');
        }

        return redirect()->back();
    }

    public function deleteDonatedItemImage($slug,$id){
        $image = DonatedItemImage::findOrFail($id);

        $donated_item = $image->donated_item;

        if(!$donated_item){
            abort(404);
        }

        $user = auth()->user();

        if($image->user_id != $user->id){
            session()->flash('error', 'Forbidden');

            return redirect()->back();
        }

        if($donated_item->bought){
            session()->flash('error', 'The Item has already been bought, you can no longer delete images from it');
            return redirect()->back();
        }        

        @unlink($this->image_path . '/donated_items/banners/' . $image->image);
        @unlink($this->image_path . '/donated_items/images/' . $image->image);
        @unlink($this->image_path . '/donated_items/slides/' . $image->image);
        @unlink($this->image_path . '/donated_items/thumbnails/' . $image->image);

        $image->delete();

        session()->flash('success', 'Image Deleted');

        return redirect()->back();
    }

    public function postPurchaseCoins(Request $request){
        $user = auth()->user();

        $this->validate($request, [
            'coins'             => 'required|numeric|min:1|max:' . config('coins.limit.purchase_coins'),
            'amount_paid'       => 'required|numeric',
            'transaction_code'  => 'required|max:255',
        ]);

        $coin_purchase_history                      = new CoinPurchaseHistory;
        $coin_purchase_history->coins               = $request->coins;
        $coin_purchase_history->amount_paid         = $request->amount_paid;
        $coin_purchase_history->transaction_code    = $request->transaction_code;
        $coin_purchase_history->user_id             = $user->id;
        $coin_purchase_history->save();

        session()->flash('success', 'Coin Purchase Requested');

        return redirect()->back();
    }
}