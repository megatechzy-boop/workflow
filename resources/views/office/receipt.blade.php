@php($biz = config('business'))
@php($invoice = $payment->invoice)
@php($client = $invoice->client)
@extends('office.doc-layout', [
    'filename' => 'paymentreceipt-'.$payment->number.'-'.Str::slug($client->name).'.pdf',
    'backMonth' => $invoice->invoice_date,
    'backUrl' => $backUrl ?? null,
])
@section('title', 'Payment receipt '.$payment->number)

@section('doc')
    <div class="muted" style="text-align:center;margin-bottom:16px">This is an electronically generated document, no signature is required.</div>
    <div class="row">
        <div>
            <h1>Payment Receipt</h1>
            <div style="margin-top:8px">
                <div><span class="muted">Payment Receipt No</span> <b>{{ $payment->number }}</b></div>
                <div><span class="muted">Receipt Date</span> {{ $payment->date->format('M d, Y') }}</div>
            </div>
        </div>
        <div style="text-align:right">
            <div style="font-size:18px;font-weight:700;color:#0A7A6C">{{ $biz['name'] }}</div>
            <span class="stamp paid" style="margin-top:8px">{{ $invoice->paid_at ? 'SETTLED' : 'PART PAYMENT' }}</span>
        </div>
    </div>

    <div class="row" style="margin-top:20px">
        <div class="box">
            <b>Issued by</b>
            <div style="font-weight:600">{{ $biz['name'] }}</div>
            <div style="white-space:pre-line">{{ $biz['address'] }}</div>
            <div>Email: {{ $biz['email'] }}</div>
            <div>Phone: {{ $biz['phone'] }}</div>
        </div>
        <div class="box">
            <b>Issued to</b>
            <div style="font-weight:600">{{ $client->name }}</div>
            @if ($client->address)<div style="white-space:pre-line">{{ $client->address }}</div>@endif
            @if ($client->contact_email)<div>Email: {{ $client->contact_email }}</div>@endif
        </div>
    </div>

    <table>
        <thead><tr><th>Payment Method</th><th>Deposited To</th><th class="r">Amount Received</th></tr></thead>
        <tbody><tr><td>{{ $payment->method }}</td><td>{{ $payment->deposited_to ?: '—' }}</td><td class="r">₹ {{ number_format($payment->amount) }}</td></tr></tbody>
    </table>

    @php($before = $invoice->payments->where('id', '<', $payment->id)->sum('amount'))
    <table>
        <thead><tr><th>Settled Invoice #</th><th class="r">Invoice Amount</th><th class="r">Amount Settled</th><th class="r">Due Amount</th></tr></thead>
        <tbody><tr>
            <td>{{ $invoice->number }}</td>
            <td class="r">₹ {{ number_format($invoice->amount) }}</td>
            <td class="r">₹ {{ number_format($payment->amount) }}</td>
            <td class="r">₹ {{ number_format(max(0, $invoice->amount - $before - $payment->amount)) }}</td>
        </tr></tbody>
    </table>

    <div class="row" style="margin-top:16px">
        <div style="flex:1">
            <div class="muted">Total amount (in words)</div>
            <div style="font-weight:600">{{ App\Support\RupeeWords::of($payment->amount) }}</div>
        </div>
        <div style="width:240px">
            <div class="row total"><span>Amount Received</span><span>₹ {{ number_format($payment->amount) }}</span></div>
        </div>
    </div>
    <div class="muted" style="margin-top:28px;text-align:center">For any enquiry, reach out via email at {{ $biz['email'] }}, call on {{ $biz['phone'] }}</div>
@endsection
