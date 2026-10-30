@php($biz = config('business'))
@php($client = $invoice->client)
@extends('office.doc-layout', [
    'filename' => 'invoice-'.$invoice->number.'-'.Str::slug($client->name).'.pdf',
    'backMonth' => $invoice->invoice_date,
    'backUrl' => $backUrl ?? null,
])
@section('title', 'Invoice '.$invoice->number)

@section('doc')
    <div class="row">
        <div>
            <h1>Invoice</h1>
            <div style="margin-top:8px">
                <div><span class="muted">Invoice No #</span> <b>{{ $invoice->number }}</b></div>
                <div><span class="muted">Invoice Date</span> {{ $invoice->invoice_date->format('M d, Y') }}</div>
                <div><span class="muted">Due Date</span> {{ $invoice->due_date->format('M d, Y') }}</div>
            </div>
        </div>
        <div style="text-align:right">
            <div style="font-size:18px;font-weight:700;color:#0A7A6C">{{ $biz['name'] }}</div>
            <span class="stamp {{ $invoice->paid_at ? 'paid' : 'unpaid' }}" style="margin-top:8px">{{ $invoice->paid_at ? 'PAID' : 'UNPAID' }}</span>
        </div>
    </div>

    <div class="row" style="margin-top:20px">
        <div class="box">
            <b>Billed By</b>
            <div style="font-weight:600">{{ $biz['name'] }}</div>
            <div style="white-space:pre-line">{{ $biz['address'] }}</div>
            @if ($biz['gstin'])<div>GSTIN: {{ $biz['gstin'] }}</div>@endif
            <div>Email: {{ $biz['email'] }}</div>
            <div>Phone: {{ $biz['phone'] }}</div>
        </div>
        <div class="box">
            <b>Billed To</b>
            <div style="font-weight:600">{{ $client->name }}</div>
            @if ($client->address)<div style="white-space:pre-line">{{ $client->address }}</div>@endif
            @if ($client->contact_email)<div>Email: {{ $client->contact_email }}</div>@endif
            @if ($client->phone)<div>Phone: {{ $client->phone }}</div>@endif
        </div>
    </div>
    <div class="muted" style="margin-top:10px">Country of Supply: India &nbsp; Place of Supply: {{ $biz['state'] }}</div>

    <table>
        <thead><tr><th>Item</th><th class="r">Quantity</th><th class="r">Rate</th><th class="r">Amount</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}. {{ $item['name'] }}@if (! empty($item['detail']))<div class="muted">({{ $item['detail'] }})</div>@endif</td>
                    <td class="r">{{ $item['qty'] }}</td>
                    <td class="r">₹ {{ number_format($item['rate'], 2) }}</td>
                    <td class="r">₹ {{ number_format($item['qty'] * $item['rate'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="row" style="margin-top:14px">
        <div style="flex:1">
            <div class="muted">Total (in words)</div>
            <div style="font-weight:600">{{ strtoupper(App\Support\RupeeWords::of($invoice->amount)) }}</div>
        </div>
        <div style="width:260px">
            @if ($invoice->gst_rate > 0)
                <div class="row"><span>Sub Total</span><span>₹ {{ number_format($invoice->subtotal(), 2) }}</span></div>
                <div class="row"><span>GST ({{ $invoice->gst_rate + 0 }}%)</span><span>₹ {{ number_format($invoice->gstAmount(), 2) }}</span></div>
            @endif
            <div class="row total" style="border-top:1px solid #e5e7eb;padding-top:6px;margin-top:4px"><span>Total (INR)</span><span>₹ {{ number_format($invoice->amount, 2) }}</span></div>
            @if ($invoice->received() > 0)
                <div class="row"><span>Paid</span><span>₹ {{ number_format($invoice->received(), 2) }}</span></div>
                <div class="row" style="font-weight:600"><span>Remaining</span><span>₹ {{ number_format($invoice->due(), 2) }}</span></div>
            @endif
        </div>
    </div>

    @if ($biz['bank']['account_number'] || $biz['bank']['upi'])
        <div class="box" style="margin-top:20px">
            <b>Bank Details</b>
            @foreach (['account_name' => 'Account Name', 'account_number' => 'Account Number', 'ifsc' => 'IFSC', 'account_type' => 'Account Type', 'bank' => 'Bank', 'upi' => 'UPI'] as $key => $label)
                @if ($biz['bank'][$key])<div><span class="muted" style="display:inline-block;width:110px">{{ $label }}</span> {{ $biz['bank'][$key] }}</div>@endif
            @endforeach
        </div>
    @endif

    <div style="margin-top:20px">
        <b style="color:#0A7A6C">Terms and Conditions</b>
        <ol style="margin:4px 0 0;padding-left:18px">
            @foreach ($biz['terms'] as $term)<li>{{ $term }}</li>@endforeach
        </ol>
    </div>
    <div class="muted" style="margin-top:24px;text-align:center">For any enquiry, reach out via email at {{ $biz['email'] }}, call on {{ $biz['phone'] }}</div>
@endsection
