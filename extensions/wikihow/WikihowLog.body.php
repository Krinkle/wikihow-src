<?php
define ('WH_LOG_DIR', '/var/log/wikihow/web'); 
define ('WH_MONTHLY_KEY_PREFIX', 'mth');
define ('WH_HOURLY_KEY_PREFIX', 'hr');
define ('WH_LOG_DELIMITER', '|');
define ('WH_TIPS_PATROL_DELETE', 'tips-p-del');
define ('WH_TIPS_PATROL_KEEP', 'tips-p-keep');
define ('WH_TIPS_PATROL_SKIP', 'tips-p-skip');

abstract class WhEventLogger {
	protected abstract function getLogFilePrefix();
	protected abstract function getTimeFactor();
	protected function getLogFileName() {
		return $this->getLogFilePrefix() . '-' . $this->getTimeFactor() . '.log';
	}
	
	protected function getMonthlyToken() {
		return WH_MONTHLY_KEY_PREFIX . '-' . date('Y-m');
	}
	
	protected function getHourlyToken() {
		return WH_HOURLY_KEY_PREFIX . '-' . date('d-H');
	}
	
	protected function logWhEvent($logEntryText) {
		$fullFileName = WH_LOG_DIR . '/' . $this->getLogFileName();
// 		wfDebugLog( 'myextension', "logEntryText=$logEntryText");
		$timeStamp = wfTimestampNow(TS_MW);
		wfErrorLog("$timeStamp". WH_LOG_DELIMITER ."$logEntryText\n", $fullFileName);
	}
}

abstract class HourlyEventLogger extends WhEventLogger {
	protected function getTimeFactor() {
		return date('Y-m-d-H');
	}
}

class UserEventLogger extends HourlyEventLogger {
	protected function getLogFilePrefix() {
		return 'user-event';
	}
	
	/**
	 * Event gets logged eventually in redis as a monthly key for an user for an event where hash
	 * Fields will have hourly counter.
	 * (non-PHPdoc)
	 * @see EventLogger::logWhEvent()
	 */
	protected function logWhEvent($eventType, $userId) {
// 		wfDebugLog( 'myextension', "eventType=$eventType, userId=$userId");
		if (empty($userId) || empty($eventType)) return;
		
		$key = $this->getMonthlyToken() . '-' . $userId;
		$field = $this->getHourlyToken() . '-u-evt-' . $eventType;
		$redisCmd = $key . WH_LOG_DELIMITER . $field . WH_LOG_DELIMITER . '1';
		parent::logWhEvent($redisCmd);
	}
	
	public function tipsPatrolDelete($userId) { $this->logWhEvent(WH_TIPS_PATROL_DELETE, $userId); }
	public function tipsPatrolKeep($userId) { $this->logWhEvent(WH_TIPS_PATROL_KEEP, $userId); }
	public function tipsPatrolSkip($userId) { $this->logWhEvent(WH_TIPS_PATROL_SKIP, $userId); }
}

class WHLogFactory {
	private static $userEventLogger;
	
	public static function getUEL() {
		if (self::$userEventLogger == null) {
			self::$userEventLogger = new UserEventLogger();
		}
		
		return self::$userEventLogger;
	}
}