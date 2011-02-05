<?php

    class socialxeserverAdminView extends socialxeserver {

        /**
         * @brief 초기화
         **/
        function init() {
        }

        /**
         * @brief 설정
         **/
        function dispSocialxeserverAdminConfig() {
            // 설정 정보를 받아옴 (module model 객체를 이용)
            $oModuleModel = &getModel('module');
            $config = $oModuleModel->getModuleConfig('socialxeserver');
            Context::set('config',$config);

            // 템플릿 파일 지정
            $this->setTemplatePath($this->module_path.'tpl');
            $this->setTemplateFile('index');
        }

		// 클라이언트 목록
		function dispSocialxeserverAdminClient(){
			// 클라이언트 목록
			$args->page = Context::get('page');
			$args->domain = Context::get('domain');
			$output = executeQuery('socialxeserver.getClientList', $args);
			if (!$output->toBool()) return $output;

			// 템플릿에 쓰기 위해서 comment_model::getTotalCommentList() 의 return object에 있는 값들을 세팅
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('client_list', $output->data);
			Context::set('page_navigation', $output->page_navigation);

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('client');
		}

		// 클라이언트 추가
		function dispSocialxeserverAdminInsertClient(){
			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('insert_client');
		}

		// 클라이언트 수정
		function dispSocialxeserverAdminModifyClient(){
			$client_srl = Context::get('client_srl');
			if (!$client_srl) return $this->stop('msg_invalid_request');

			// 클라이언트 정보 얻기
			$args->client_srl = $client_srl;
			$output = executeQuery('socialxeserver.getClient', $args);
			if (!$output->toBool()) return $output;
			if (!$output->data) return $this->stop('msg_invalid_request');

			// 정보 가공
			$client_info = $output->data;
			$domain_array = explode(',', $client_info->domain);
			foreach($domain_array as &$val){
				$val = trim($val);
			}

			// 템플릿에 사용하기 위해 셋
			Context::set('client_info', $client_info);
			Context::set('domain_list', $domain_array);

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('modify_client');
		}

        // 요즘 액세스 토큰 얻기
        function dispSocialxeserverAdminGetYozmAccessToken(){
            // 세션 세팅
            $this->session->setSession('yozmgetaccess', true);

            // 로그인 URL을 얻는다.
            unset($output);
            $output = $this->communicator->providerManager->getLoginUrl('yozm');
            if (!$output->toBool()) return $output;
            $url = $output->get('url');

            // 리다이렉트
            header('Location: ' . $url);
            Context::close();
            exit;
        }

        // 콜백
        function dispSocialxeserverAdminCallback(){
            $output = $this->communicator->access();
            Context::set('access_token', $output->get('access_token'));

            // 템플릿 파일 지정
            $this->setTemplatePath($this->module_path.'tpl');
            $this->setTemplateFile('yozmgetaccess');

            // HTML 형식
            Context::setRequestMethod('HTML');
        }
    }
?>
