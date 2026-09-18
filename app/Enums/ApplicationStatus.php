<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case AwaitingPayment = 'awaiting_payment';
    case AwaitingFeedback = 'awaiting_feedback';
    case AwaitingDecision = 'awaiting_decision';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ReturnedForAmendment = 'returned_for_amendment';
    case Withdrawn = 'withdrawn';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Dihantar',
            self::InReview => 'Dalam Semakan',
            self::AwaitingPayment => 'Menunggu Bayaran',
            self::AwaitingFeedback => 'Menunggu Maklum Balas',
            self::AwaitingDecision => 'Menunggu Keputusan',
            self::Approved => 'Diluluskan',
            self::Rejected => 'Ditolak',
            self::ReturnedForAmendment => 'Pindaan Diperlukan',
            self::Withdrawn => 'Ditarik Balik',
            self::Closed => 'Selesai',
        };
    }
}
