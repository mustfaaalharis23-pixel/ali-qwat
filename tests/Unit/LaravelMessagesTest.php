<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LaravelMessagesTest extends TestCase
{
    /**
     * EN: Laravel's validation messages are shown in Arabic when the language is Arabic.
     * AR: رسائل الـ validation مال Laravel تطلع بالعربي من تكون اللغة عربي.
     */
    public function test_validation_messages_are_in_arabic(): void
    {
        App::setLocale('ar');

        $this->assertSame('حقل الاسم مطلوب.', $this->requiredNameMessage('الاسم'));
    }

    /**
     * EN: Laravel's validation messages are shown in English when the language is English.
     * AR: رسائل الـ validation مال Laravel تطلع بالإنكليزي من تكون اللغة إنكليزي.
     */
    public function test_validation_messages_are_in_english(): void
    {
        App::setLocale('en');

        $this->assertSame('The name field is required.', $this->requiredNameMessage('name'));
    }

    /**
     * EN: Validates an empty required name field and returns its error message.
     * AR: يفحص حقل اسم مطلوب وفارغ ويرجع رسالة الخطأ مالته.
     */
    private function requiredNameMessage(string $attribute): string
    {
        $validator = Validator::make([], ['name' => 'required'], [], ['name' => $attribute]);

        return $validator->errors()->first('name');
    }
}
