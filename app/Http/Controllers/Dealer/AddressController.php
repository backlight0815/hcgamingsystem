<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Address;

class AddressController extends Controller
{
    //StoreAddress

    public function MyAddress()
    {
        $user = auth()->user(); // Get the currently logged-in user
        $myaddress = $user->address;
        if (!$myaddress) {
            // Create a new instance of the Address model with default values
            $myaddress = new Address();
            $myaddress->name = '';
            $myaddress->street = '';
            $myaddress->street_2 = '';
            $myaddress->zipcode = '';
            $myaddress->country = ''; // Set the default image path or use 'null'
            $myaddress->state = ''; // Set the default image path or use 'null'

        }

        return view('agent.address.address_all', compact('myaddress'));
    }

    public function UpdateAddress(Request $request)
    {
        $user = auth()->user(); // Get the currently logged-in user
    // Validation rules for address fields
    $rules = [
        'name' => 'nullable|string|regex:/^[a-zA-Z\s]+$/|max:255',
        'address_line_1' => 'nullable|string|max:255',
        'address_line_2' => 'nullable|string|max:255',
        'zipcode' => 'nullable|string|max:10',
        'country' => 'nullable|string|regex:/^[a-zA-Z\s]+$/|max:255', // Only letters and spaces allowed
        'state' => 'nullable|string|regex:/^[a-zA-Z\s]+$/|max:255',
    ];

    // Apply the validation rules
    $request->validate($rules, [
        'zipcode.max' => 'The zipcode field may not be greater than :max characters.',
        'name.regex' => 'The name field may only contain letters and spaces.',

        'country.regex' => 'The country field may only contain letters and spaces.',
        'country.max' => 'The country field may not be greater than :max characters.',
        'state.max' => 'The state field may not be greater than :max characters.',
        'state.regex' => 'The state field may only contain letters and spaces.',

    ]);
        // Find the address associated with the user
        $address = $user->address;

        // If the record is found (update)
        if ($address) {
            // Update the address fields
            $address->name = $request->name;
            $address->street = $request->address_line_1;
            $address->street_2 = $request->address_line_2;
            $address->zipcode = $request->zipcode;
            $address->city = $request->country;
            $address->state = $request->state;

            $address->save();

            $notification = [
                'message' => 'Address Updated',
                'alert-type' => 'success'
            ];
        } else {
            // If the address is not found, create a new one
            $address = new Address([
                'user_id' => $user->id, // Assign the user ID to the address
                'name' => $request->name,
                'street' => $request->address_line_1,
                'street_2' => $request->address_line_2,
                'zipcode' => $request->zipcode,
                'city' => $request->country,
                'state' => $request->state,
            ]);

            $user->address()->save($address);

            $notification = [
                'message' => 'Address Inserted',
                'alert-type' => 'success'
            ];
        }

        return redirect()->route('dealer.address')->with($notification);
    }

}
