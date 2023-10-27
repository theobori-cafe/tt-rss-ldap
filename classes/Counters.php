<?php
class Counters {

	/**
	 * @return array<int, array<string, int|string>>
	 */
	static function get_all(): array {
		return [
			...self::get_global(),
			...self::get_virt(),
			...self::get_labels(),
			...self::get_feeds(),
			...self::get_cats(),
		];
	}

	/**
	 * @param array<int> $feed_ids
	 * @param array<int> $label_ids
	 * @return array<int, array<string, int|string>>
	 */
	static function get_conditional(array $feed_ids = null, array $label_ids = null): array {
		return [
			...self::get_global(),
			...self::get_virt(),
			...self::get_labels($label_ids),
			...self::get_feeds($feed_ids),
			...self::get_cats(is_array($feed_ids) ? Feeds::_cats_of($feed_ids, $_SESSION["uid"], true) : null)
		];
	}

	/**
	 * @return array<int, int>
	 */
	static private function get_cat_children(int $cat_id, int $owner_uid): array {
		$unread = 0;
		$marked = 0;

		$cats = ORM::for_table('ttrss_feed_categories')
					->where('owner_uid', $owner_uid)
					->where('parent_cat', $cat_id)
					->find_many();

		foreach ($cats as $cat) {
			list ($tmp_unread, $tmp_marked) = self::get_cat_children($cat->id, $owner_uid);

			$unread += $tmp_unread + Feeds::_get_cat_unread($cat->id, $owner_uid);
			$marked += $tmp_marked + Feeds::_get_cat_marked($cat->id, $owner_uid);
		}

		return [$unread, $marked];
	}

	/**
	 * @param array<int> $cat_ids
	 * @return array<int, array<string, int|string>>
	 */
	private static function get_cats(array $cat_ids = null): array {
		$ret = [];

		/* Labels category */

		$cv = array("id" => Feeds::CATEGORY_LABELS, "kind" => "cat",
			"counter" => Feeds::_get_cat_unread(Feeds::CATEGORY_LABELS));

		array_push($ret, $cv);

		$pdo = Db::pdo();

		if (is_array($cat_ids)) {
			if (count($cat_ids) == 0)
				return [];

			$cat_ids_qmarks = arr_qmarks($cat_ids);

			$sth = $pdo->prepare("SELECT fc.id,
					SUM(CASE WHEN unread THEN 1 ELSE 0 END) AS count,
						SUM(CASE WHEN marked THEN 1 ELSE 0 END) AS count_marked,
						(SELECT COUNT(id) FROM ttrss_feed_categories fcc
						WHERE fcc.parent_cat = fc.id) AS num_children
				FROM ttrss_feed_categories fc
					LEFT JOIN ttrss_feeds f ON (f.cat_id = fc.id)
					LEFT JOIN ttrss_user_entries ue ON (ue.feed_id = f.id)
				WHERE fc.owner_uid = ? AND fc.id IN ($cat_ids_qmarks)
				GROUP BY fc.id
			UNION
				SELECT 0,
					SUM(CASE WHEN unread THEN 1 ELSE 0 END) AS count,
						SUM(CASE WHEN marked THEN 1 ELSE 0 END) AS count_marked,
						0
				FROM ttrss_feeds f, ttrss_user_entries ue
				WHERE f.cat_id IS NULL AND
					ue.feed_id = f.id AND
					ue.owner_uid = ?");

			$sth->execute([$_SESSION['uid'], ...$cat_ids, $_SESSION['uid']]);

		} else {
			$sth = $pdo->prepare("SELECT fc.id,
					SUM(CASE WHEN unread THEN 1 ELSE 0 END) AS count,
						SUM(CASE WHEN marked THEN 1 ELSE 0 END) AS count_marked,
						(SELECT COUNT(id) FROM ttrss_feed_categories fcc
						WHERE fcc.parent_cat = fc.id) AS num_children
				FROM ttrss_feed_categories fc
					LEFT JOIN ttrss_feeds f ON (f.cat_id = fc.id)
					LEFT JOIN ttrss_user_entries ue ON (ue.feed_id = f.id)
				WHERE fc.owner_uid = :uid
				GROUP BY fc.id
			UNION
				SELECT 0,
					SUM(CASE WHEN unread THEN 1 ELSE 0 END) AS count,
						SUM(CASE WHEN marked THEN 1 ELSE 0 END) AS count_marked,
						0
				FROM ttrss_feeds f, ttrss_user_entries ue
				WHERE f.cat_id IS NULL AND
					ue.feed_id = f.id AND
					ue.owner_uid = :uid");

			$sth->execute(["uid" => $_SESSION['uid']]);
		}

		while ($line = $sth->fetch()) {
			if ($line["num_children"] > 0) {
				list ($child_counter, $child_marked_counter) = self::get_cat_children($line["id"], $_SESSION["uid"]);
			} else {
				$child_counter = 0;
				$child_marked_counter = 0;
			}

			$cv = [
				"id" => (int)$line["id"],
				"kind" => "cat",
				"markedcounter" => (int) $line["count_marked"] + $child_marked_counter,
				"counter" => (int) $line["count"] + $child_counter
			];

			array_push($ret, $cv);
		}

		return $ret;
	}

	/**
	 * @param array<int> $feed_ids
	 * @return array<int, array<string, int|string>>
	 */
	private static function get_feeds(array $feed_ids = null): array {
		$span = Tracer::start(__METHOD__);

		$ret = [];

		$pdo = Db::pdo();

		if (is_array($feed_ids)) {
			if (count($feed_ids) == 0)
				return [];

			$feed_ids_qmarks = arr_qmarks($feed_ids);

			$sth = $pdo->prepare("SELECT f.id,
					f.title,
					".SUBSTRING_FOR_DATE."(f.last_updated,1,19) AS last_updated,
					f.last_error,
					SUM(CASE WHEN unread THEN 1 ELSE 0 END) AS count,
					SUM(CASE WHEN marked THEN 1 ELSE 0 END) AS count_marked
				FROM ttrss_feeds f, ttrss_user_entries ue
				WHERE f.id = ue.feed_id AND ue.owner_uid = ? AND f.id IN ($feed_ids_qmarks)
				GROUP BY f.id");

			$sth->execute([$_SESSION['uid'], ...$feed_ids]);
		} else {
			$sth = $pdo->prepare("SELECT f.id,
					f.title,
					".SUBSTRING_FOR_DATE."(f.last_updated,1,19) AS last_updated,
					f.last_error,
					SUM(CASE WHEN unread THEN 1 ELSE 0 END) AS count,
					SUM(CASE WHEN marked THEN 1 ELSE 0 END) AS count_marked
				FROM ttrss_feeds f, ttrss_user_entries ue
				WHERE f.id = ue.feed_id AND ue.owner_uid = :uid
				GROUP BY f.id");

			$sth->execute(["uid" => $_SESSION['uid']]);
		}

		while ($line = $sth->fetch()) {

			$id = $line["id"];
			$last_updated = TimeHelper::make_local_datetime($line['last_updated'], false);

			if (Feeds::_has_icon($id)) {
				$ts = filemtime(Feeds::_get_icon_file($id));
			} else {
				$ts = 0;
			}

			// hide default un-updated timestamp i.e. 1970-01-01 (?) -fox
			if ((int)date('Y') - (int)date('Y', strtotime($line['last_updated'] ?? '')) > 2)
				$last_updated = '';

			$cv = [
				"id" => $id,
				"updated" => $last_updated,
				"counter" => (int) $line["count"],
				"markedcounter" => (int) $line["count_marked"],
				"ts" => (int) $ts
			];

			$cv["error"] = $line["last_error"];
			$cv["title"] = truncate_string($line["title"], 30);

			array_push($ret, $cv);

		}

		$span->end();

		return $ret;
	}

	/**
	 * @return array<int, array<string, int|string>>
	 */
	private static function get_global(): array {
		$span = Tracer::start(__METHOD__);

		$ret = [
			[
				"id" => "global-unread",
				"counter" => (int) Feeds::_get_global_unread()
			]
		];

		$subcribed_feeds = ORM::for_table('ttrss_feeds')
			->where('owner_uid', $_SESSION['uid'])
			->count();

		array_push($ret, [
			"id" => "subscribed-feeds",
			"counter" => $subcribed_feeds
		]);

		$span->end();

		return $ret;
	}

	/**
	 * @return array<int, array<string, int|string>>
	 */
	private static function get_virt(): array {
		$span = Tracer::start(__METHOD__);

		$ret = [];

		foreach ([Feeds::FEED_ARCHIVED, Feeds::FEED_STARRED, Feeds::FEED_PUBLISHED,
			Feeds::FEED_FRESH, Feeds::FEED_ALL] as $feed_id) {

			$count = Feeds::_get_counters($feed_id, false, true);

			if (in_array($feed_id, [Feeds::FEED_ARCHIVED, Feeds::FEED_STARRED, Feeds::FEED_PUBLISHED]))
				$auxctr = Feeds::_get_counters($feed_id, false);
			else
				$auxctr = 0;

			$cv = [
				"id" => $feed_id,
				"counter" => (int) $count,
				"auxcounter" => (int) $auxctr
			];

			if ($feed_id == Feeds::FEED_STARRED)
				$cv["markedcounter"] = $auxctr;

			array_push($ret, $cv);
		}

		$feeds = PluginHost::getInstance()->get_feeds(Feeds::CATEGORY_SPECIAL);

		if (is_array($feeds)) {
			foreach ($feeds as $feed) {
				/** @var IVirtualFeed $feed['sender'] */

				if (!implements_interface($feed['sender'], 'IVirtualFeed'))
					continue;

				$cv = [
					"id" => PluginHost::pfeed_to_feed_id($feed['id']),
					"counter" => $feed['sender']->get_unread($feed['id'])
				];

				if (method_exists($feed['sender'], 'get_total'))
					$cv["auxcounter"] = $feed['sender']->get_total($feed['id']);

				array_push($ret, $cv);
			}
		}

		$span->end();
		return $ret;
	}

	/**
	 * @param array<int> $label_ids
	 * @return array<int, array<string, int|string>>
	 */
	static function get_labels(array $label_ids = null): array {
		$span = Tracer::start(__METHOD__);

		$ret = [];

		$pdo = Db::pdo();

		if (is_array($label_ids)) {
			if (count($label_ids) == 0)
				return [];

			$label_ids_qmarks = arr_qmarks($label_ids);

			$sth = $pdo->prepare("SELECT id,
						caption,
						SUM(CASE WHEN u1.unread = true THEN 1 ELSE 0 END) AS count_unread,
						SUM(CASE WHEN u1.marked = true THEN 1 ELSE 0 END) AS count_marked,
						COUNT(u1.unread) AS total
				FROM ttrss_labels2 LEFT JOIN ttrss_user_labels2 ON
					(ttrss_labels2.id = label_id)
						LEFT JOIN ttrss_user_entries AS u1 ON u1.ref_id = article_id AND u1.owner_uid = ?
							WHERE ttrss_labels2.owner_uid = ? AND ttrss_labels2.id IN ($label_ids_qmarks)
								GROUP BY ttrss_labels2.id, ttrss_labels2.caption");
			$sth->execute([$_SESSION["uid"], $_SESSION["uid"], ...$label_ids]);
		} else {
			$sth = $pdo->prepare("SELECT id,
						caption,
						SUM(CASE WHEN u1.unread = true THEN 1 ELSE 0 END) AS count_unread,
						SUM(CASE WHEN u1.marked = true THEN 1 ELSE 0 END) AS count_marked,
						COUNT(u1.unread) AS total
				FROM ttrss_labels2 LEFT JOIN ttrss_user_labels2 ON
					(ttrss_labels2.id = label_id)
						LEFT JOIN ttrss_user_entries AS u1 ON u1.ref_id = article_id AND u1.owner_uid = :uid
							WHERE ttrss_labels2.owner_uid = :uid
								GROUP BY ttrss_labels2.id, ttrss_labels2.caption");
			$sth->execute([":uid" => $_SESSION['uid']]);
		}

		while ($line = $sth->fetch()) {

			$id = Labels::label_to_feed_id($line["id"]);

			$cv = [
				"id" => $id,
				"counter" => (int) $line["count_unread"],
				"auxcounter" => (int) $line["total"],
				"markedcounter" => (int) $line["count_marked"],
				"description" => $line["caption"]
			];

			array_push($ret, $cv);
		}

		$span->end();
		return $ret;
	}
}
