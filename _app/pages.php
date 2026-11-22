<?php
// Landing page definitions. Copy is first person (the agent speaking). type: consult | guide | value
const CONSENT_VERSION = '2026-11-22';
function page_defs(): array {
    return [
    'buyer-consult' => ['type' => 'consult', 'form' => 'buyer-consult', 'title' => 'Complimentary 15-Minute Buyer Consultation',
        'sub' => 'Free, no obligation',
        'body' => ["Every buyer has a different situation, different needs and different goals. When I take the time to understand yours, I can share useful information and resources to help your purchase go smoothly and support a strong financial result.",
                   "A home is a major asset, and I am passionate about guiding buyers through the process. This 15-minute consultation comes with no obligation. I offer it out of a sincere wish to help."],
        'button' => 'Request My Consultation', 'time_field' => true],
    'seller-consult' => ['type' => 'consult', 'form' => 'seller-consult', 'title' => 'Complimentary 15-Minute Seller Consultation',
        'sub' => "Let's get you to SOLD",
        'body' => ["Every home and every seller is different. In a short conversation I will learn about your property, your timeline and your goals, then share the information and resources that can help you sell for the best result.",
                   "There is no cost and no obligation. I offer this consultation because I want to help you make a confident decision."],
        'button' => 'Request My Consultation', 'time_field' => true],
    'seller-guide' => ['type' => 'guide', 'form' => 'seller-guide', 'title' => "FREE Seller Guide To Maximize Your Home's Value",
        'sub' => 'Perfect for preparing your home for sale', 'h2' => "Follow The Guide To Maximize Your Home's Value",
        'body' => ["Preparing your home for the market can raise its value and help bring offers sooner. Modest updates such as fresh paint, better lighting, minor repairs and modern hardware change how buyers see a home.",
                   "Curb appeal, decluttering, a deep clean and staging help buyers picture themselves living there, and small flooring or energy-efficiency upgrades add appeal. Follow the steps in the guide to help your home stand out in a competitive market."],
        'button' => 'SUBMIT', 'file' => 'how-to-sell-a-home.pdf', 'file_label' => 'How to sell a home'],
    'buyer-guide' => ['type' => 'guide', 'form' => 'buyer-guide', 'title' => 'FREE Buyer Guide: Avoid These Buyer Mistakes',
        'sub' => 'Perfect to help you buy stress free, with confidence', 'h2' => 'Follow The Guide To Make Home Buying Stress Free',
        'body' => ["Knowing the common pitfalls before you start helps you stay confident and succeed in a competitive market. This guide walks through the mistakes buyers make most often and how to avoid them."],
        'button' => 'SUBMIT', 'file' => 'how-to-buy-a-home.pdf', 'file_label' => 'How to buy a home'],
    'value' => ['type' => 'value', 'form' => 'value', 'title' => 'Curious What Your Home Is Worth Today?',
        'sub' => "Get Your FREE Home Market Value Report", 'h2' => "Know your home's current value. Sent to you. No obligation.",
        'body' => [], 'button' => 'Get My Report'],
    'buyer-steps' => ['type' => 'video', 'vkey' => 'vid_steps', 'title' => 'What Are The Right Steps To Buying A Home?',
        'sub' => 'Helping you with your questions is something I love to do.', 'body' => ['Watch my video to learn the right steps to buying a home.']],
    'buyer-broker-agreement' => ['type' => 'video', 'vkey' => 'vid_broker', 'title' => 'Why Is A Buyer Broker Agreement Required?',
        'sub' => 'Helping you with your questions is something I love to do.', 'body' => ["Watch my video to help you understand the purpose and benefits of a buyer's brokerage agreement."]],
    'avoid-buyer-agent-fee' => ['type' => 'video', 'vkey' => 'vid_avoid', 'title' => 'Can I Avoid Paying For A Buyer\'s Agent To Help Me?',
        'sub' => 'Helping you with your questions is something I love to do.', 'body' => ["Watch my video to understand how buyer agent compensation works and what your options are."]],
    ];
}
