<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class AdminAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return $this->user()?->can('admin') === true; // 管理者だけ
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'work_date' => ['required','date'],
            'clock_in' => ['nullable'], // H:i 受け取り
            'clock_out' => ['nullable'], // H:i 受け取り
            'breaks' => ['array'],
            'breaks.*.start' => ['nullable'], // H:i
            'breaks.*.end' => ['nullable'], // H:i
            'note' => ['required','string','max:2000'], // 備考必須
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => '備考を記入してください。',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $dateStr = $this->input('work_date');
            $tz = config('app.timezone', 'Asia/Tokyo');

            $clockIn  = $this->toDateTime($dateStr, $this->input('clock_in'),  $tz);
            $clockOut = $this->toDateTime($dateStr, $this->input('clock_out'), $tz);

            // 出勤 > 退勤 / 退勤 < 出勤
            if ($clockIn && $clockOut && $clockIn->gte($clockOut)) {
                $validator->errors()->add('clock_in',  '出勤時間もしくは退勤時間が不適切な値です');
                $validator->errors()->add('clock_out', '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 休憩（最大2枠想定）
            $breaks = (array) $this->input('breaks', []);
            foreach ($breaks as $i => $b) {
                $start = $this->toDateTime($dateStr, $b['start'] ?? null, $tz);
                $end   = $this->toDateTime($dateStr, $b['end']   ?? null, $tz);

                // 休憩開始の不整合（出勤より前 / 退勤より後）
                if ($start) {
                    if (($clockIn && $start->lt($clockIn)) || ($clockOut && $start->gt($clockOut))) {
                        $validator->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                    }
                }

                // 休憩終了の不整合（退勤より後、または開始≧終了）
                if (($start && $end && $start->gte($end)) || ($end && $clockOut && $end->gt($clockOut))) {
                    $validator->errors()->add("breaks.$i.end", '休憩時間もしくは退勤時間が不適切な値です');
                }
            }
        });
    }

    private function toDateTime(?string $ymd, ?string $hm, string $tz): ?Carbon
    {
        if (!$ymd || !$hm) return null;
        try {
            [$h, $m] = array_pad(explode(':', $hm), 2, null);
            if ($h === null || $m === null) return null;
            return Carbon::parse($ymd, $tz)->setTime((int)$h, (int)$m, 0);
        } catch (\Throwable $e) {
            return null;
        }
    }
}