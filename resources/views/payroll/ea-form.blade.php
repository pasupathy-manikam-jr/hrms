@php
    $rm = fn (int $sen) => number_format($sen / 100, 2);
    $myKad = $employee->id_type === 'passport' ? '' : $employee->id_number;
    $passport = $employee->id_type === 'passport' ? $employee->id_number : '';
    $joinedThisYear = $employee->date_of_joining?->year === $year;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>EA {{ $year }} — {{ $employee->user->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111; }
        .top { width: 100%; }
        .top td { vertical-align: top; }
        .ea { font-size: 30px; font-weight: bold; border: 2px solid #111; padding: 2px 12px; }
        h1 { font-size: 11px; text-align: center; margin: 4px 0 2px; }
        .sub { text-align: center; margin-bottom: 8px; }
        h2 { font-size: 9px; background: #e5e7eb; padding: 3px 5px; margin: 8px 0 2px; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines td { padding: 2px 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        td.no { width: 18px; }
        td.amt { width: 90px; text-align: right; white-space: nowrap; }
        .en { color: #555; font-style: italic; }
        .total td { font-weight: bold; border-top: 1px solid #111; }
        .sign { margin-top: 18px; width: 100%; }
        .sign td { vertical-align: top; width: 50%; padding-top: 4px; }
    </style>
</head>
<body>
    <table class="top">
        <tr>
            <td>
                No. Majikan E / <span class="en">Employer's No.</span>: <strong>{{ $employer['employerTaxNumber'] ?: '-' }}</strong><br>
                No. Pengenalan Cukai Pekerja / <span class="en">Employee's TIN</span>: <strong>{{ $employee->tax_payer_id ?: '-' }}</strong>
            </td>
            <td style="text-align: right">(C.P.8A) <span class="ea">EA</span></td>
        </tr>
    </table>

    <h1>PENYATA SARAAN DARIPADA PENGGAJIAN BAGI TAHUN BERAKHIR 31 DISEMBER {{ $year }}</h1>
    <div class="sub en">STATEMENT OF REMUNERATION FROM EMPLOYMENT FOR THE YEAR ENDED 31 DECEMBER {{ $year }}</div>

    <h2>A. BUTIRAN PEKERJA / <span class="en">PARTICULARS OF EMPLOYEE</span></h2>
    <table class="lines">
        <tr><td class="no">1.</td><td>Nama penuh pekerja / <span class="en">Full name of employee</span></td><td><strong>{{ mb_strtoupper($employee->user->name) }}</strong></td></tr>
        <tr><td class="no">2.</td><td>Jawatan / <span class="en">Job designation</span></td><td>{{ $employee->designation?->name }}</td></tr>
        <tr><td class="no">3.</td><td>No. kakitangan / <span class="en">Staff no.</span></td><td>{{ $employee->employee_id }}</td></tr>
        <tr><td class="no">4.</td><td>No. K.P. baru / <span class="en">New IC no.</span></td><td>{{ $myKad }}</td></tr>
        <tr><td class="no">5.</td><td>No. pasport / <span class="en">Passport no.</span></td><td>{{ $passport }}</td></tr>
        <tr><td class="no">6.</td><td>No. KWSP / <span class="en">EPF no.</span></td><td>{{ $employee->epf_number }}</td></tr>
        <tr><td class="no">7.</td><td>No. PERKESO / <span class="en">SOCSO no.</span></td><td>{{ $employee->socso_number ?: $myKad }}</td></tr>
        <tr><td class="no">8.</td><td>Tarikh mula berkhidmat (jika dalam tahun ini) / <span class="en">Date of commencement (if during the year)</span></td><td>{{ $joinedThisYear ? $employee->date_of_joining->format('d/m/Y') : '' }}</td></tr>
    </table>

    <h2>B. PENDAPATAN PENGGAJIAN, MANFAAT DAN TEMPAT KEDIAMAN / <span class="en">EMPLOYMENT INCOME, BENEFITS AND LIVING ACCOMMODATION</span> (RM)</h2>
    <table class="lines">
        <tr><td class="no">1.</td><td>(a) Gaji kasar, upah atau gaji cuti (termasuk gaji lebih masa) / <span class="en">Gross salary, wages or leave pay (including overtime pay)</span></td><td class="amt">{{ $rm($figures['salary']) }}</td></tr>
        <tr><td></td><td>(b) Fi (termasuk fi pengarah), komisen atau bonus / <span class="en">Fees (including director fees), commission or bonus</span></td><td class="amt">0.00</td></tr>
        <tr><td></td><td>(c) Tip kasar, perkuisit, penerimaan sagu hati atau elaun-elaun lain / <span class="en">Gross tips, perquisites, awards/rewards or other allowances</span></td><td class="amt">{{ $rm($figures['allowances']) }}</td></tr>
        <tr><td></td><td>(d) Cukai pendapatan yang dibayar oleh majikan / <span class="en">Income tax borne by the employer</span></td><td class="amt">0.00</td></tr>
        <tr><td></td><td>(e) Skim Opsyen Saham Pekerja (ESOS) / <span class="en">Employee Share Option Scheme</span></td><td class="amt">0.00</td></tr>
        <tr><td></td><td>(f) Ganjaran / <span class="en">Gratuity</span></td><td class="amt">0.00</td></tr>
        <tr><td class="no">2.</td><td>Butiran bayaran tunggakan dan lain-lain bagi tahun terdahulu / <span class="en">Arrears and others for preceding years</span></td><td class="amt">0.00</td></tr>
        <tr><td class="no">3.</td><td>Manfaat berupa barangan / <span class="en">Benefits in kind</span></td><td class="amt">0.00</td></tr>
        <tr><td class="no">4.</td><td>Nilai tempat kediaman / <span class="en">Value of living accommodation</span></td><td class="amt">0.00</td></tr>
        <tr><td class="no">5.</td><td>Bayaran balik daripada kumpulan wang yang tidak diluluskan / <span class="en">Refund from unapproved pension or provident fund</span></td><td class="amt">0.00</td></tr>
        <tr><td class="no">6.</td><td>Pampasan kerana kehilangan pekerjaan / <span class="en">Compensation for loss of employment</span></td><td class="amt">0.00</td></tr>
        <tr class="total"><td></td><td>JUMLAH / <span class="en">TOTAL</span></td><td class="amt">{{ $rm($figures['gross']) }}</td></tr>
    </table>

    <h2>C. PENCEN DAN LAIN-LAIN / <span class="en">PENSION AND OTHERS</span> (RM)</h2>
    <table class="lines">
        <tr><td class="no">1.</td><td>Pencen / <span class="en">Pension</span></td><td class="amt">0.00</td></tr>
        <tr><td class="no">2.</td><td>Anuiti atau bayaran berkala lain / <span class="en">Annuities or other periodical payments</span></td><td class="amt">0.00</td></tr>
    </table>

    <h2>D. JUMLAH POTONGAN / <span class="en">TOTAL DEDUCTION</span> (RM)</h2>
    <table class="lines">
        <tr><td class="no">1.</td><td>Potongan cukai bulanan (PCB) yang dibayar kepada LHDNM / <span class="en">Monthly tax deductions (MTD) remitted to LHDNM</span></td><td class="amt">{{ $rm($figures['pcb']) }}</td></tr>
        <tr><td class="no">2.</td><td>Arahan potongan CP38 / <span class="en">CP38 deductions</span></td><td class="amt">{{ $rm($figures['cp38']) }}</td></tr>
        <tr><td class="no">3.</td><td>Zakat yang dibayar melalui potongan gaji / <span class="en">Zakat paid via salary deduction</span></td><td class="amt">{{ $rm($figures['zakat']) }}</td></tr>
        <tr><td class="no">4.</td><td>Jumlah tuntutan potongan oleh pekerja melalui Borang TP1 / <span class="en">Total claim for deduction by employee via Form TP1</span></td><td class="amt">0.00</td></tr>
        <tr><td class="no">5.</td><td>Bilangan anak yang layak untuk pelepasan cukai / <span class="en">Number of children qualified for tax relief</span></td><td class="amt">{{ $employee->tax_children }}</td></tr>
    </table>

    <h2>E. CARUMAN YANG DIBAYAR OLEH PEKERJA / <span class="en">CONTRIBUTIONS PAID BY EMPLOYEE</span> (RM)</h2>
    <table class="lines">
        <tr><td class="no">1.</td><td>Kumpulan Wang Simpanan Pekerja (KWSP) — bahagian pekerja / <span class="en">Employees Provident Fund — employee's share</span></td><td class="amt">{{ $rm($figures['epf']) }}</td></tr>
        <tr><td class="no">2.</td><td>PERKESO (PERKESO dan SIP) — bahagian pekerja / <span class="en">SOCSO (SOCSO and EIS) — employee's share</span></td><td class="amt">{{ $rm($figures['socso']) }}</td></tr>
    </table>

    <h2>F. JUMLAH ELAUN / PERKUISIT / PEMBERIAN / MANFAAT YANG DIKECUALIKAN CUKAI / <span class="en">TOTAL TAX EXEMPT ALLOWANCES / PERQUISITES / GIFTS / BENEFITS</span> (RM)</h2>
    <table class="lines">
        <tr><td></td><td></td><td class="amt">{{ $rm($figures['exempt']) }}</td></tr>
    </table>

    <table class="sign">
        <tr>
            <td>
                Nama majikan / <span class="en">Name of employer</span>:<br>
                <strong>{{ $employer['titleText'] }}</strong>
                @if ($employer['employerRegistrationNumber'])
                    ({{ $employer['employerRegistrationNumber'] }})
                @endif
            </td>
            <td>
                Nama, jawatan dan tandatangan pegawai / <span class="en">Name, designation and signature of officer</span>:<br><br><br>
                ______________________________<br>
                Tarikh / <span class="en">Date</span>: {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>
</body>
</html>
