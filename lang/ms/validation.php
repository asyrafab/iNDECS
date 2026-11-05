<?php

return [
    'required' => ':attribute wajib diisi.',
    'array' => ':attribute mesti dalam bentuk senarai.',
    'date' => ':attribute mesti merupakan tarikh yang sah.',
    'email' => ':attribute mesti menggunakan alamat e-mel yang sah.',
    'integer' => ':attribute mesti merupakan nombor bulat.',
    'numeric' => ':attribute mesti merupakan nombor.',
    'string' => ':attribute mesti merupakan teks.',
    'in' => ':attribute yang dipilih tidak sah.',
    'file' => ':attribute mesti merupakan fail.',
    'uploaded' => ':attribute gagal dimuat naik. Pastikan saiz fail tidak melebihi had dan cuba lagi.',
    'mimes' => ':attribute mesti berjenis fail: :values.',
    'mimetypes' => ':attribute mesti berjenis fail: :values.',
    'exists' => ':attribute yang dipilih tidak sah.',
    'distinct' => ':attribute mempunyai nilai berulang.',
    'boolean' => ':attribute mesti ya atau tidak.',
    'regex' => 'Format :attribute tidak sah.',
    'gt' => [
        'numeric' => ':attribute mesti lebih besar daripada :value.',
    ],
    'min' => [
        'array' => ':attribute mesti mengandungi sekurang-kurangnya :min item.',
        'numeric' => ':attribute mesti sekurang-kurangnya :min.',
        'string' => ':attribute mesti mengandungi sekurang-kurangnya :min aksara.',
    ],
    'max' => [
        'array' => ':attribute tidak boleh mengandungi lebih daripada :max item.',
        'numeric' => ':attribute tidak boleh melebihi :max.',
        'string' => ':attribute tidak boleh melebihi :max aksara.',
        'file' => ':attribute tidak boleh melebihi :max kilobait.',
    ],
];
