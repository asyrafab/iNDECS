{{-- Upload-first part 2: details the background check filled, for draft-prefill.js to merge into an open form. --}}
@php
    $prefillPayload = ['version' => $draft->version, 'fields' => new \stdClass, 'sources' => new \stdClass];
    foreach ($draft->prefill_sources ?? [] as $prefillField => $prefillSource) {
        $prefillPayload['fields']->{$prefillField} = $draft->{$prefillField};
        $prefillPayload['sources']->{$prefillField} = $prefillSource['name'];
    }
@endphp
<template data-prefill='{{ json_encode($prefillPayload, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }}'></template>
