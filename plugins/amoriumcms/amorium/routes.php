<?php

Route::post('amorium/form/submit', \AmoriumCms\Amorium\Classes\SubmitForm::class)
    ->middleware('web');
