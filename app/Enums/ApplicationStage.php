<?php

namespace App\Enums;

enum ApplicationStage: string
{
    case Registration = 'registration';
    case CompletenessCheck = 'completeness_check';
    case FeeAssessment = 'fee_assessment';
    case PaymentVerification = 'payment_verification';
    case TechnicalCirculation = 'technical_circulation';
    case PlanningAssessment = 'planning_assessment';
    case CommitteeMeeting = 'committee_meeting';
    case Decision = 'decision';
    case ApprovalPreparation = 'approval_preparation';
    case ApprovalIssued = 'approval_issued';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Pendaftaran',
            self::CompletenessCheck => 'Semakan Lengkap',
            self::FeeAssessment => 'Penilaian Fi',
            self::PaymentVerification => 'Pengesahan Bayaran',
            self::TechnicalCirculation => 'Edaran Teknikal',
            self::PlanningAssessment => 'Penilaian Perancangan',
            self::CommitteeMeeting => 'Mesyuarat Jawatankuasa',
            self::Decision => 'Keputusan',
            self::ApprovalPreparation => 'Penyediaan Kelulusan',
            self::ApprovalIssued => 'Kelulusan Dikeluarkan',
            self::Closed => 'Selesai',
        };
    }
}
