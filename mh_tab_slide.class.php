<?php
	/**
	 * @class mh_tab_slide
	 * @author zero (zero@nzeo.com)
	 * @brief 다중 모듈 선택시 탭 형식으로 표시
	 * @version 0.5
	 **/

	class mh_tab_slide extends WidgetHandler {

		/**
		 * @brief 위젯의 실행 부분
		 *
		 * ./widgets/위젯/conf/info.xml 에 선언한 extra_vars를 args로 받는다
		 * 결과를 만든후 print가 아니라 return 해주어야 한다
		 **/
		function proc($args) {
			// 대상 모듈 (mid_list는 기존 위젯의 호환을 위해서 처리하는 루틴을 유지. module_srls로 위젯에서 변경)
			$oModuleModel = getModel('module');
			if($args->mid_list ?? null) {
				$mid_list = explode(",",$args->mid_list);
				if(count($mid_list)) {
					$module_srls = $oModuleModel->getModuleSrlByMid($mid_list);
					if(count($module_srls)) $args->module_srls = implode(',',$module_srls);
					else $args->module_srls = null;
				} 
			}

			// 선택된 모듈이 없으면 실행 취소
			if(!($args->module_srls ?? null)) return Context::getLang('msg_not_founded');

			// 정렬 대상
			$args->order_target = in_array($args->order_target ?? null, array('list_order','update_order')) 
				? $args->order_target 
				: 'list_order';

			// 정렬 순서
			$args->order_type = in_array($args->order_type ?? null, array('asc','desc')) 
				? $args->order_type 
				: 'asc';

			$args->subject_cut_size = $args->subject_cut_size ?? 20;
			$args->list_count = $args->list_count ?? 5;
			$args->thumbnail_type = $args->thumbnail_type ?? 'fill';
			$args->thumbnail_width = $args->thumbnail_width ?? 100;
			$args->thumbnail_height = $args->thumbnail_height ?? 100;
			$args->thumbnail_zoom = $args->thumbnail_zoom ?? 2;
			$args->duration_new = $args->duration_new ?? 24;

			// Slide 설정값
			$args->view_no = $args->view_no ?? 2;
			$args->mo_view = $args->mo_view ?? 1;
			$args->scroll_no = $args->scroll_no ?? 1;
			$args->autospeed = $args->autospeed ?? 5000;
			$args->speed = $args->speed ?? 3000;
			$args->rows = $args->rows ?? 1;
			$args->center_padding = $args->center_padding ?? '50p';

			// Slick 옵션
			$args->autoplay = $args->autoplay ?? 'true';
			$args->infinite = $args->infinite ?? 'true';
			$args->dots = $args->dots ?? 'true';
			$args->vertical = $args->vertical ?? 'false';
			$args->center_m = $args->center_m ?? 'false';
			$args->fade = $args->fade ?? 'false';

			$oModuleModel = getModel('module');
			$oDocumentModel = getModel('document');

			// 모듈 목록을 구함
			$module_list = $oModuleModel->getModulesInfo($args->module_srls);
			if(!count($module_list)) return Context::getLang('msg_not_founded');

			// 각 모듈별로 먼저 정리 시작
			$site_domain = array(0 => Context::getDefaultUrl());
			$site_module_info = Context::get('site_module_info');
			if($site_module_info) $site_domain[$site_module_info->site_srl] = $site_module_info->domain;

			$mid_module_list = array(); // 배열 초기화
			foreach($module_list as $key => $val) {
				if(!isset($site_domain[$val->site_srl])) {
					$site_info = $oModuleModel->getSiteInfo($val->site_srl);
					$site_domain[$site_info->site_srl] = $site_info->domain;
				}
				$module_list[$key]->domain = $site_domain[$val->site_srl];
				$mid_module_list[$val->module_srl] = $key;
			}

			$tab_list = array(); // 배열 초기화
			$module_srl = explode(',',$args->module_srls);
			for($i=0;$i<count($module_srl);$i++) {
				if(isset($mid_module_list[$module_srl[$i]])) {
					$tab_list[$mid_module_list[$module_srl[$i]]] = $module_list[$mid_module_list[$module_srl[$i]]];
				}
			}

			// 각 모듈에 해당하는 문서들을 구함
			$obj = new stdClass(); // null 대신 stdClass 객체 생성
			$obj->list_count = $args->list_count;
			$obj->sort_index = $args->order_target;
			$obj->order_type = $args->order_type=="desc"?"asc":"desc";
			
			$newest_tab = array(); // 배열 초기화
			foreach($tab_list as $key => $value) {
				$mid = $key;
				$module_srl = $value->module_srl;
				$browser_title = $value->browser_title;

				$tab_list[$key]->category_list = $oDocumentModel->getCategoryList($module_srl); 

				$obj->module_srl = $module_srl;
				$output = executeQueryArray("widgets.mh_tab_slide.getNewestDocuments", $obj);
				$data = array(); // 배열 초기화

				if($output->data && count($output->data)) {
					foreach($output->data as $k => $v) {
						$oDocument = null;
						$oDocument = new documentItem();
						$oDocument->setAttribute($v);
						$data[$k] = $oDocument;
						if(!isset($newest_tab[$key])) {
							$newest_tab[$key] = $oDocument->get('last_update');
						}
					}
					$tab_list[$key]->document_list = $data;
				} else {
					unset($tab_list[$key]);
				}
			}
			
			$sorted_tab_list = array(); // 배열 초기화
			if(count($newest_tab)) {
				arsort($newest_tab);
				foreach($newest_tab as $module_srl => $last_update) {
					$sorted_tab_list[$module_srl] = $tab_list[$module_srl];
				}
			} else {
				$sorted_tab_list = $tab_list;
			}

			// $args를 복사하여 widget_info 생성
			$widget_info = clone $args;
			// 시간을 초 단위로 변환 (widget_info에만 적용)
			$widget_info->duration_new = (int)$args->duration_new * 60 * 60;

			Context::set('widget_info', $widget_info);
			Context::set('tab_list', $sorted_tab_list);

			// 템플릿의 스킨 경로를 지정 (skin, colorset에 따른 값을 설정)
			$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
			Context::set('colorset', $args->colorset);

			// 템플릿 파일을 지정
			$tpl_file = 'list';

			// 템플릿 컴파일
			$oTemplate = TemplateHandler::getInstance();
			return $oTemplate->compile($tpl_path, $tpl_file);
		}
	}
?>