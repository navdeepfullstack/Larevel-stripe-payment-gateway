<?php

namespace App\Http\Controllers\Website;

use Stripe;
use Auth;
use Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Helpers\StripeHelper;
use Illuminate\Http\Exceptions\HttpResponseException;
use Stripe\StripeClient;
use App\Models\User;
use App\Models\PaymentDetails;


class StripeController extends Controller
{
    public function __construct()
    {
        Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        $this->stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

    }
  
   public function stripeToken($inputArr)
    {
        //Create Stripe Token
        $token = $this->stripe->tokens->create([
          "card" => [
            "number" => $inputArr['card_number'],
            "exp_month" => $inputArr['exp_month'],
            "exp_year" => $inputArr['exp_year'],
            "cvc" => $inputArr['cvc'],
            "name" => $inputArr['name']
          ],
        ]);

        return $token['id'];
    }



    /**
     * @var $request object of request class
     * @var $user object of user class
     * @return object with add user card
     * This function use to api add user card
     */

    public function addUserCard(Request $request)
    {
         $requestArr = $request->all();

       if (Auth::user()) {
       $userObj=Auth::user();
           $validator = Validator::make($request->all(), [
              'card_number' => 'required',
              'exp_month' => 'required',
              'exp_year' => 'required',
              'cvc'=>'required',
              'name'=>'required'
            ]);
             if($validator->fails()){ 
               return redirect()->back()->withInput($request->all())->with('errors', $validator->errors()->first()); 
            }
               

        $input = $request->all();
        try{
             $card_token = $this->stripeToken($input);
        }
       catch (\Exception $ex)
        {
          return response()->json(['status' => false, 'inavlid' => $ex->getMessage()], 200);
         }

        try
        {
            if (!$userObj->stripe_id)
            {
                $customer = \Stripe\Customer::create([
                  'email' => $userObj->email,
                  'description' => '+ ' . $userObj->mobile_number 
                ]);
                $userObj->stripe_id = $customer['id'];
            }
            else
            {
                \Stripe\Customer::update(
                  $userObj->stripe_id,
                ['email' => $userObj->email]
                );
            }

            // tok_visa is the token which will generate in client side
            $stripeCard = \Stripe\Customer::createSource(
              $userObj->stripe_id,
            ['source' => $card_token]
            );
        }
        catch (\Exception $ex)
        {

           return response()->json(['status' => false, 'inavlid' => $ex->getMessage()], 200);
            
        }

        if (isset($stripeCard['id']) && $stripeCard['id'] != '')
        {
            /*$userCardArr = [
              'user_id' => $userObj->id,
              'card_token' => $stripeCard['id']
            ];*/

            //$userCardObj = UserCard::create($userCardArr);
            $userCardObj = $userObj->save();

            if ($userCardObj)
            {
                return response()->json(['status' => true, 'message' => 'Your card has been added successfully'], 200);
                
              
            }
            else
            {
                return response()->json(['status' => false, 'errors' => 'Unable to add card. Please try again later.'], 200);
               
            }
        }
        else
        {
            return response()->json(['status' => false, 'errors' => 'Unable to add card. Please try again later.'], 200);
        }
      }
      else{
        return response()->json(['status' => 2, 'message' => 'Login required'], 200);
      }
    }

     /**
     * @var $request object of request class
     * @var $user object of user class
     * @return object with delete user card
     * This function use to api delete user card
     */

       public function deleteUserCard(Request $request)
    {
       

       $requestArr = $request->all();
         $userObj = $request->user();
        if (!$userObj) {
            return returnNotAuthorizedResponse('User is not authorized');
        }
        $rules=[
          'card_id' => 'required',
        ];

        $validator = Validator::make($requestArr, $rules);
        if ($validator->fails()) {
            $errorMessages = $validator->errors()->all();
            throw new HttpResponseException(returnValidationErrorResponse($errorMessages[0]));
        }

        if (!$userObj->stripe_id)
        {
            $result = array(
              "statusCode" => 200, // $this-> successStatus
              "message" => 'No card found of the user.'
            );
            return response()->json($result);
        }

        try
        {
            $hasDeleted = \Stripe\Customer::deleteSource(
              $userObj->stripe_id,
              $request->input('card_id')
            );
        }
        catch (\Exception $ex)
        {
            $result = array(
              "statusCode" => 401,
              "message" => $ex->getMessage()
            );
            return response()->json($result);
        }

        // $userCard = UserCard::where('user_id', $user->id)->where('id', $request->input('card_id'))->first();
        if ($hasDeleted)
        {
            $result = array(
              "statusCode" => 200,
              "message" => "Your card has been deleted successfully!"
            );
        }
        else
        {
            $result = array(
              "statusCode" => 500,
              "message" => "Unable to delete card."
            );
        }
        return response()->json($result);
    }

   

   

      
 public function getUrl()
    {
        try {
            
            $stripe = new \Stripe\StripeClient(env("STRIPE_SECRET_kEY"));

             $stripe_account = $stripe->accounts->create(['type' => 'standard']);
                
            $response = $stripe->accountLinks->create([
                "account" => $stripe_account->id,
                "refresh_url" => "https://example.com/reauth",
                "return_url" =>
                env("APP_URL") . "/return-url/" . $stripe_account->id,
                "type" => "account_onboarding",
            ]);
             
           
            
            $returnArr = [];


            if (isset($response->url)) {
                $returnArr["redriect_url"] = $response->url;
                $returnArr["stripe_id"] = $stripe_account->id;

                \Log::debug("stripe id: " . $stripe_account->id);
            }
            return returnSuccessResponse(
                "Get redriect url successfully.",
                $returnArr
            );
        } catch (Exception $e) {
            return returnErrorResponse($e->message());
        }
    }

    public function getDetail(Request $request)
    {
        try {
            $rules = [
                "stripe_id" => "required",
            ];
            $inputArr = $request->all();
            $validator = Validator::make($inputArr, $rules);
            if ($validator->fails()) {
                $errorMessages = $validator->errors()->all();
                throw new HttpResponseException(
                    returnValidationErrorResponse($errorMessages[0])
                );
            }
            $user_detail = User::where(
                "stripe_id",
                $request->stripe_id
            )->first();


            if (!empty($user_detail)) {
                $jsonDataArr = $user_detail->json_data ?  json_decode($user_detail->json_data, true) : null;
                $settingArr = $jsonDataArr && $jsonDataArr['settings'] ? $jsonDataArr['settings'] : null;
                $dashboardArr = $settingArr && $settingArr['dashboard'] ? $settingArr['dashboard'] : null;
                $display_name = $dashboardArr && $dashboardArr['display_name'] ? $dashboardArr['display_name'] : null;
                if ($display_name) {
                    $user_detail->display_name = $display_name;
                }

                $account_status = false;
                if ($jsonDataArr['charges_enabled'] && $jsonDataArr['payouts_enabled']) {
                    $account_status = true;
                } elseif (!$jsonDataArr['charges_enabled'] && !$jsonDataArr['payouts_enabled']) {
                    $account_status = false;
                }
                $user_detail->account_status = $account_status;

                return returnSuccessResponse(
                    "Get user detail successfully.",
                    $user_detail
                );
            }
            return returnNotFoundResponse("User detail not found.");
        } catch (Exception $e) {
            return returnErrorResponse($e->message());
        }
    }

    public function getToken()
    {
        try {
            \Stripe\Stripe::setApiKey(env("STRIPE_SECRET_kEY"));
            $connectionToken = \Stripe\Terminal\ConnectionToken::create();
            $token = json_encode(["secret" => $connectionToken->secret]);
            return returnSuccessResponse(
                "Get connection token successfully.",
                $token
            );
        } catch (Exception $e) {
            return returnErrorResponse($e->message());
        }
    }

      /**
     * @var $request object of request class
     * @var $user object of user class
     * @return object with create payment when job completed 
     * This function use to api create payment when job completed 
     */
    public function makePayment(Request $request)
    {

        try {
            $rules = [
                "currency" => "required",
                "amount" => "required",
            ];
            $inputArr = $request->all();
            $validator = Validator::make($inputArr, $rules);
            if ($validator->fails()) {
                $errorMessages = $validator->errors()->all();
                throw new HttpResponseException(
                    returnValidationErrorResponse($errorMessages[0])
                );
            }
            $stripe = new \Stripe\StripeClient(env("STRIPE_SECRET_kEY"));
            $merchant_payment = $request->amount * 1.2 / 100;

            $payment_response = $stripe->paymentIntents->create([

                'currency' => $request->currency,
                'amount' => round($request->amount * 100, 2),
                'description' => $request->description,
                'payment_method_types' => ['card_present'],
                'capture_method' => 'manual',
                'application_fee_amount' => (int)round($merchant_payment * 100, 2),
                'transfer_data' => [
                    'destination' => $request->connected_id,  // Specify the connected account ID
                ],
            ]);

            //$confirmedPaymentIntent = $payment_response->confirm();

            //['stripe_account' =>$request->connected_id]);
            return returnSuccessResponse(
                "Make payment sent successfully.",
                $payment_response
            );
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return returnErrorResponse($e->getMessage());
        }
    }

    public function capturePayment(Request $request)
    {
        try {
            $rules = [
                "payment_id" => "required",


            ];
            $inputArr = $request->all();
            $validator = Validator::make($inputArr, $rules);
            if ($validator->fails()) {
                $errorMessages = $validator->errors()->all();
                throw new HttpResponseException(
                    returnValidationErrorResponse($errorMessages[0])
                );
            }
            $stripe = new \Stripe\StripeClient(env("STRIPE_SECRET_kEY"));


            $payment  =  $stripe->paymentIntents->capture($request->payment_id, []);
            if ($payment->status === "succeeded") {
                $user_detail = User::where('stripe_id', $payment->transfer_data->destination)->first();

                $transaction = new Transaction();
                $transaction->user_id               = $user_detail->id;
                $transaction->payment_intent        = $payment->id;
                $transaction->payment_method        = $payment->payment_method;
                $transaction->charge_id             = $payment->latest_charge;
                $transaction->transaction_id        = $payment->transfer_data->destination;
                $transaction->amount                = $payment->amount;
                $transaction->currency              = 'aud';
                $transaction->status                = $payment->status;
                $transaction->transactionDate       = $payment->created;
                $transaction->product_description   = ($request->product_description) ? $request->product_description : null;
                $transaction->save();
                // Payment was successful
                // Update your database or perform other necessary actions

                return returnSuccessResponse("Payment confirmed!", $payment);
            } else {
                // Payment failed or not valid
                return returnErrorResponse(
                    "Payment confirmation failed!.",
                    $payment
                );
            }
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return returnErrorResponse($e->getMessage());
        }
    }




}
