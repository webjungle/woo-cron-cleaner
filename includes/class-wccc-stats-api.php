<?php
defined('ABSPATH') || exit;
class WCCC_Stats_API {
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register']);
    }
    public static function register() {
        register_rest_route('wccc/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'data'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    public static function data($request) {
        $from = $request->get_param('from') ?: date('Y-m-d', strtotime('-30 days'));
        $to   = $request->get_param('to')   ?: date('Y-m-d');
        $log = get_option('wccc_cleanup_log', []);
        $errors = get_option('wccc_error_log', []);
        $perDay=[]; $failedDay=[]; $total=0; $failedTotal=0;
        foreach($log as $row){
            $ts=strtotime($row['time']);
            if($ts<strtotime("$from 00:00:00")||$ts>strtotime("$to 23:59:59")) continue;
            $d=date('Y-m-d', $ts);
            $perDay[$d]=($perDay[$d] ?? 0)+$row['count'];
            $total+=$row['count'];
        }
        foreach($errors as $e){
            $ts=strtotime($e['time']);
            if($ts<strtotime("$from 00:00:00")||$ts>strtotime("$to 23:59:59")) continue;
            $d=date('Y-m-d',$ts);
            $failedDay[$d]=($failedDay[$d] ?? 0)+1;
            $failedTotal++;
        }
        $days=count($perDay)?:1;
        $avgDay=round($total/$days,1);
        $avgWeek=round($total/max(1,$days/7),1);
        $avgMonth=round($total/max(1,$days/30),1);
        $peakCount=0;$peakDate='';
        if(!empty($perDay)){
            $peakCount=max($perDay);
            $peakDates=array_keys($perDay,$peakCount);
            $peakDate=reset($peakDates);
        }
        return rest_ensure_response([
            'avg'=>['day'=>$avgDay,'week'=>$avgWeek,'month'=>$avgMonth],
            'donut'=>['complete'=>$total-$failedTotal,'failed'=>$failedTotal],
            'peak'=>['count'=>$peakCount,'date'=>$peakDate],
            'savedSec'=>round($total*0.3,1),
        ]);
    }
}
WCCC_Stats_API::init();
