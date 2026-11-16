{{-- Upload-first part 2: details the background check filled, for draft-prefill.js to merge into an open form. --}}
@php
    // base: the last version the applicant (not a fill) wrote. A form at or after it may take the newer version;
    // an older form must still get the "Draf telah berubah" conflict on save.
    $prefillBase = (int) \Illuminate\Support\Facades\DB::table('core.applicant_draft_revisions')->where('draft_id', $draft->id)
        ->whereRaw("coalesce((snapshot->>'prefill')::boolean, false) = false")->max('version');
    $prefillPayload = ['version' => $draft->version, 'base' => $prefillBase, 'fields' => new \stdClass, 'sources' => new \stdClass];
    foreach ($draft->prefill_sources ?? [] as $prefillField => $prefillSource) {
        $prefillPayload['fields']->{$prefillField} = $draft->{$prefillField};
        $prefillPayload['sources']->{$prefillField} = $prefillSource['name'];
    }
@endphp
<template data-prefill='{{ json_encode($prefillPayload, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }}'></template>
