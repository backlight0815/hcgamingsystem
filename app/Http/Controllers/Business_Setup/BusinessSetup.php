<?php

namespace App\Http\Controllers\Business_Setup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Models\TngQrCode;
class BusinessSetup extends Controller
{

    public function AllPaymentMethods(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Payment Methods Setup', 'url' => route('all.blog')],

        ];
        $paymentsmethods = PaymentMethod::latest()->get();
        return view('admin.blogs.blogs_all',compact('paymentsmethods','breadcrumbData'));

            }//end method
    public function storePaymentMethod(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->name = $request->name;
        $paymentMethod->save();

        return redirect()->route('business-setup.index')->with('success', 'Payment method added successfully.');
    }
    public function storeQrCode(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|file|image', // Adjust validation as needed
        ]);

        $file = $request->file('qr_code');
        $path = $file->store('qr_codes', 'public'); // Store the file in storage/app/public/qr_codes

        $qrCode = new TngQrCode();
        $qrCode->file_path = $path;
        $qrCode->save();

        return redirect()->route('business-setup.index')->with('success', 'QR Code uploaded successfully.');
    }
}

