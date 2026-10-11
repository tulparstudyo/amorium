<?php

Route::post('amorium/form/submit', \Amorium\Form\Classes\SubmitForm::class)
    ->middleware('web');
