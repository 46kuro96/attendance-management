<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class AttendanceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'work_date'         => ['required','date'],
            'clock_in'          => ['nullable'],
            'clock_out'         => ['nullable'],
            'breaks'            => ['array'],
            'breaks.*.start'    => ['nullable'],
            'breaks.*.end'      => ['nullable'],
            'reason'            => ['required','string','max:2000'], // ユーザーの申請理由
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => '備考を記入してください',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // 入力（H:i）を日付付きにして比較する（work_date + H:i）
            $dateStr = $this->input('work_date'); // 'Y-m-d'
            $tz = config('app.timezone', 'Asia/Tokyo');

            $clockIn  = $this->toDateTime($dateStr, $this->input('clock_in'), $tz);
            $clockOut = $this->toDateTime($dateStr, $this->input('clock_out'), $tz);

            // 出勤 > 退勤 / 退勤 < 出勤 → エラー
            if ($clockIn && $clockOut && $clockIn->gte($clockOut)) {
                // どちらにも同じ文言を付けておく（どこで見ても分かるように）
                $validator->errors()->add('clock_in',  '出勤時間もしくは退勤時間が不適切な値です');
                $validator->errors()->add('clock_out', '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 休憩の検証（最大2枠）
            $breaks = (array) $this->input('breaks', []);
            foreach ($breaks as $i => $b) {
                $start = $this->toDateTime($dateStr, $b['start'] ?? null, $tz);
                $end   = $this->toDateTime($dateStr, $b['end']   ?? null, $tz);

                // 休憩開始が出勤より前 or 退勤より後 → 「休憩時間が不適切な値です」
                if ($start) {
                    if (($clockIn && $start->lt($clockIn)) || ($clockOut && $start->gt($clockOut))) {
                        $validator->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                    }
                }

                // 休憩終了が退勤より後 → 「休憩時間もしくは退勤時間が不適切な値です」
                if ($end && $clockOut && $end->gt($clockOut)) {
                    $validator->errors()->add("breaks.$i.end", '休憩時間もしくは退勤時間が不適切な値です');
                }
                // 休憩終了 < 休憩開始 → 「休憩時間もしくは退勤時間が不適切な値です」
                if ($start && $end && $start->gte($end)) {
                    $validator->errors()->add("breaks.$i.end", '休憩時間もしくは退勤時間が不適切な値です');
                }
            }
        });
    }

    // carbonオブジェクトに変換
    private function toDateTime(?string $ymd, ?string $hm, string $tz): ?Carbon
    {
        if (!$ymd || !$hm) return null;
        // $hm は 'H:i' 想定。パース失敗は null 扱い
        try {
            [$h, $m] = array_pad(explode(':', $hm), 2, null);
            if ($h === null || $m === null) return null;
            return Carbon::parse($ymd, $tz)->setTime((int)$h, (int)$m, 0);
        } catch (\Throwable $e) {
            return null;
        }
    }
}