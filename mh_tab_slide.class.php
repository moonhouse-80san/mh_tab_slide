<?php
/**
 * @class mh_tab_slide
 * @brief 다중 모듈의 최신글을 탭 형식으로 표시하는 위젯
 * @version 0.5 (PHP 8 호환, 기본값 적용)
 **/

class mh_tab_slide extends WidgetHandler {
	/**
	 * @brief 위젯 실행
	 *
	 * @param object|null $args 위젯 설정 값 (nullable 처리)
	 * @return string 컴파일된 위젯 HTML
	 **/
	public function proc(?object $args = null): string {
		if (!$args) {
			return 'Invalid widget parameters';
		}

		// 모델 가져오기
		$oModuleModel = getModel('module');
		$oDocumentModel = getModel('document');

		// 기본값 설정
		$defaults = [
			'order_target' => 'list_order',
			'order_type' => 'asc',
			'subject_cut_size' => 20,
			'list_count' => 6,
			'thumbnail_type' => 'fill',
			'thumbnail_width' => 100,
			'thumbnail_height' => 100,
			'thumbnail_zoom' => 2,
			'view_no' => 3,
			'mo_view' => 1,
			'scroll_no' => 1,
			'autospeed' => 5000,
			'speed' => 3000,
			'rows' => 1,
			'center_padding' => '50p',
			'autoplay' => 'true',
			'infinite' => 'true',
			'dots' => 'true',
			'vertical' => 'false',
			'center_m' => 'false',
			'fade' => 'false',
			'duration_new' => 24 * 60 * 60 // 24시간을 초 단위로 변환
		];

		// $args와 $defaults 병합 (기본값 적용)
		$settings = array_merge($defaults, (array) $args);

		// 위젯 정보 초기화
		$widget_info = (object) $settings;

		// mid_list 처리 (이전 버전 호환성 유지)
		if (!empty($widget_info->mid_list)) {
			$mid_list = explode(",", $widget_info->mid_list);
			if (!empty($mid_list)) {
				$module_srls = $oModuleModel->getModuleSrlByMid($mid_list);
				$widget_info->module_srls = !empty($module_srls) ? implode(',', $module_srls) : null;
			}
		}

		// 모듈이 선택되지 않은 경우
		if (empty($widget_info->module_srls)) {
			return Context::getLang('msg_not_founded') ?? 'Module not found';
		}

		// 모듈 정보 가져오기
		$module_list = $oModuleModel->getModulesInfo($widget_info->module_srls);
		if (empty($module_list)) {
			return Context::getLang('msg_not_founded') ?? 'No modules found';
		}

		// 도메인 정보 처리
		$site_domain = $this->getSiteDomains($module_list, $oModuleModel);

		// 모듈 목록 처리
		$tab_list = $this->processModuleList($module_list, $site_domain);

		// 문서 목록 가져오기
		$tab_list = $this->getDocumentList($tab_list, $widget_info, $oDocumentModel);

		// 컨텍스트 설정
		Context::set('widget_info', $widget_info);
		Context::set('tab_list', $tab_list);

		// 템플릿 처리
		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $widget_info->skin);
		Context::set('colorset', $widget_info->colorset);

		$oTemplate = TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, 'list');
	}

	/**
	 * 사이트 도메인 정보 가져오기
	 */
	private function getSiteDomains(array $module_list, object $oModuleModel): array {
		$site_domain = [0 => Context::getDefaultUrl()];
		$site_module_info = Context::get('site_module_info');

		if ($site_module_info) {
			$site_domain[$site_module_info->site_srl] = $site_module_info->domain;
		}

		foreach ($module_list as $module) {
			if (!isset($site_domain[$module->site_srl])) {
				$site_info = $oModuleModel->getSiteInfo($module->site_srl);
				$site_domain[$module->site_srl] = $site_info->domain ?? '';
			}
		}

		return $site_domain;
	}

	/**
	 * 모듈 목록 처리
	 */
	private function processModuleList(array $module_list, array $site_domain): array {
		$tab_list = [];

		foreach ($module_list as $module) {
			$module->domain = $site_domain[$module->site_srl] ?? '';
			$tab_list[$module->module_srl] = $module;
		}

		return $tab_list;
	}

	/**
	 * 문서 목록 가져오기 및 최신 수정 순으로 탭 정렬
	 */
	private function getDocumentList(array $tab_list, object $widget_info, object $oDocumentModel): array {
		$obj = new stdClass();
		$obj->list_count = $widget_info->list_count;
		$obj->sort_index = $widget_info->order_target;
		$obj->order_type = $widget_info->order_type === "desc" ? "asc" : "desc";

		$newest_tab = [];

		foreach ($tab_list as $module_srl => &$module_info) {
			$obj->module_srl = intval($module_srl);
			$output = executeQueryArray("widgets.mh_tab_slide.getNewestDocuments", $obj);

			if (!empty($output->data)) {
				$module_info->document_list = [];

				foreach ($output->data as $document_data) {
					$oDocument = new documentItem(); // documentItem 객체 생성
					$oDocument->setAttribute($document_data);
					$module_info->document_list[] = $oDocument;

					// 최신 수정 시간 저장
					if (!isset($newest_tab[$module_srl]) || 
						$newest_tab[$module_srl] < $oDocument->get('last_update')) {
						$newest_tab[$module_srl] = $oDocument->get('last_update');
					}
				}
			} else {
				unset($tab_list[$module_srl]);
			}
		}

		// 최신 수정 시간 기준으로 탭 정렬
		if (!empty($newest_tab)) {
			arsort($newest_tab);

			$sorted_tab_list = [];
			foreach ($newest_tab as $module_srl => $last_update) {
				$sorted_tab_list[$module_srl] = $tab_list[$module_srl];
			}

			return $sorted_tab_list;
		}

		return $tab_list;
	}
}