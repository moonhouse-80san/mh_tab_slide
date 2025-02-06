<?php
/**
 * @class mh_tab_slide
 * @brief 다중 모듈의 최신글을 탭 형식으로 표시하는 위젯
 * @version 0.5 (PHP 8 호환)
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

		// mid_list 처리 (이전 버전 호환성 유지)
		if (!empty($args->mid_list)) {
			$mid_list = explode(",", $args->mid_list);
			if (!empty($mid_list)) {
				$module_srls = $oModuleModel->getModuleSrlByMid($mid_list);
				$args->module_srls = !empty($module_srls) ? implode(',', $module_srls) : null;
			}
		}

		// 모듈이 선택되지 않은 경우
		if (empty($args->module_srls)) {
			return Context::getLang('msg_not_founded') ?? 'Module not found';
		}

		// 위젯 기본 설정 초기화
		$widget_info = $this->initializeWidgetInfo($args);

		// 모듈 정보 가져오기
		$module_list = $oModuleModel->getModulesInfo($args->module_srls);
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
		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
		Context::set('colorset', $args->colorset);

		$oTemplate = TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, 'list');
	}

	/**
	 * 위젯 설정 초기화
	 */
	private function initializeWidgetInfo(object $args): object {
		$widget_info = new stdClass();

		// 기본 설정
		$widget_info->order_target = in_array($args->order_target, ['list_order', 'update_order']) 
			? $args->order_target 
			: 'list_order';
		$widget_info->order_type = in_array($args->order_type, ['asc', 'desc']) 
			? $args->order_type 
			: 'asc';
		$widget_info->subject_cut_size = (int)($args->subject_cut_size ?? 20);
		$widget_info->list_count = (int)($args->list_count ?? 5);
		
		// 썸네일 설정
		$widget_info->thumbnail_type = $args->thumbnail_type ?? 'fill';
		$widget_info->thumbnail_width = (int)($args->thumbnail_width ?? 100);
		$widget_info->thumbnail_height = (int)($args->thumbnail_height ?? 100);
		$widget_info->thumbnail_zoom = (int)($args->thumbnail_zoom ?? 2);

		// 슬라이드 설정
		$widget_info->view_no = (int)($args->view_no ?? 2);
		$widget_info->scroll_no = (int)($args->scroll_no ?? 1);
		$widget_info->autospeed = (int)($args->autospeed ?? 5000);
		$widget_info->speed = (int)($args->speed ?? 3000);
		$widget_info->rows = (int)($args->rows ?? 1);
		$widget_info->center_padding = $args->center_padding ?? '50p';
		$widget_info->autoplay = $args->autoplay ?? 'true';
		$widget_info->infinite = $args->infinite ?? 'true';
		$widget_info->dots = $args->dots ?? 'true';
		$widget_info->vertical = $args->vertical ?? 'false';
		$widget_info->center_m = $args->center_m ?? 'false';
		$widget_info->fade = $args->fade ?? 'false';

		// 표시 옵션 설정
		$widget_info->display_author = $args->display_author ?? '';
		$widget_info->display_regdate = $args->display_regdate ?? '';
		$widget_info->display_readed_count = $args->display_readed_count ?? '';
		$widget_info->display_voted_count = $args->display_voted_count ?? '';
		$widget_info->thumd_nails = $args->thumd_nails ?? '';
		$widget_info->zoom = $args->zoom ?? '';
		$widget_info->file_icon = $args->file_icon ?? '';

		// 최근 글 표시 시간 설정
		$widget_info->duration_new = (int)($args->duration_new ?? 12) * 60 * 60;
		$widget_info->tab_text = $args->tab_text ?? '';

		return $widget_info;
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