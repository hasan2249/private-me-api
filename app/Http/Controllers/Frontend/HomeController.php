<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Class HomeController.
 */
class HomeController extends Controller
{
    /**
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('frontend.index');
    }

    public function privacyAr()
    {
        return view('frontend.ar-privace-policy');
    }

    public function privacyEn()
    {
        return view('frontend.en-privace-policy');
    }

    function payment($price)
    {
        return $this->request($price);
    }

    // Payment Function:
    // url tutorial: https://wordpresshyperpay.docs.oppwa.com/tutorials/integration-guide
    // COPYandPAY
    function request($price)
    {
        $user = auth()->user();
        $url = "https://eu-test.oppwa.com/v1/checkouts";
        $data = "entityId=8ac7a4c980447ded01804683885c0897" .
            "&amount=$price" .
            "&currency=SAR" .
            "&paymentType=DB" .
            "&testMode=EXTERNAL" .
            "&merchantTransactionId=" . uniqid() . "" .
            "&billing.street1= Althwora ST" .
            "&billing.city=jaddah" .
            "&billing.state= city" .
            "&billing.country=SA";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization:Bearer OGFjN2E0Yzk4MDQ0N2RlZDAxODA0NjgzMjRiMTA4NzN8RUpjWng3QVBtMg=='
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // this should be set to true in production
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);

        return view('frontend.payment', ["response" => $responseData, "price" => $price]);
    }

    function checkStatus(Request $request)
    {
        $url = "https://eu-test.oppwa.com/v1/checkouts/$request->id/payment";
        $url .= "?entityId=8ac7a4c980447ded01804683885c0897";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization:Bearer OGFjN2E0Yzk4MDQ0N2RlZDAxODA0NjgzMjRiMTA4NzN8RUpjWng3QVBtMg=='
        ));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // this should be set to true in production
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return view('frontend.after_payment', ["responseData" => $responseData]);
    }
}
