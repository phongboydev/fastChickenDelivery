<?php

namespace App\Traits;

trait ValidateTraits
{

    /**
     * Validate login
     *
     * @param $request
     * @return void
     */
    public function validateLogin($request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);
    }

    /**
     * Validate register
     *
     * @param $request
     * @return void
     */
    public function validateRegister($request)
    {
        $request->validate([
            'email' => 'required',
            'username' => 'required',
            'name' => 'required',
            'password' => 'required',
            'confirm_password' => 'required|same:password'
        ]);
    }

    /**
     * Validate forgot password
     *
     * @param $request
     * @return void
     */
    public function validateForgotPassword($request)
    {
        $request->validate([
            'email' => 'required',
        ]);
    }

    /**
     * Validate reset password
     *
     * @param $request
     * @return void
     */
    public function validateResetPassword($request)
    {
        $request->validate([
            'password' => 'required',
            'confirm_password' => 'required|same:password'
        ]);
    }

    /**
     * Validate category
     *
     * @param $request
     * @return void
     */
    public function validateCategory($request)
    {
        $request->validate([
            'name' => 'required',
        ]);
    }

    /**
     * Validate brand
     *
     * @param $request
     * @return void
     */
    public function validateBrand($request)
    {
        $request->validate([
            'name' => 'required',
        ]);
    }

    /**
     * Validate product
     *
     * @param $request
     * @return void
     */
    public function validateProduct($request)
    {
        $request->validate([
            'name' => 'required',
            'brand' => 'required',
            'category' => 'required',
            'price' => 'required',
        ]);
    }

    /**
     * Validate product
     *
     * @param $request
     * @return void
     */
    public function validateDoctor($request)
    {
        $request->validate([
            'name' => 'required',
            'level' => 'required',
        ]);
    }

    /**
     * Validate product
     *
     * @param $request
     * @return void
     */
    public function validateAccount($request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'username' => 'required',
            'phone' => 'required',
        ]);
    }

    /**
     * Validate product
     *
     * @param $request
     * @return void
     */
    public function validateDetailInvoiceImport($request)
    {
        $request->validate([
            'product' => 'required',
            'quantity' => 'required',
            'price' => 'required',
        ]);
    }

    /**
     * Validate service
     *
     * @param $request
     * @return void
     */
    public function validateService($request)
    {
        $request->validate([
            'name' => 'required',
        ]);
    }
}
