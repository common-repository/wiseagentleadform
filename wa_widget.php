<?php

class WA_Elementor_Widget extends \Elementor\Widget_Base {

	private $wa_forms_resp = null;

	public function get_name() {
        return 'Capture Forms';
    }

	public function get_title() {
        return 'Wise Agent Capture Form';
    }

	public function get_icon() {
        return 'eicon-form-horizontal';
    }

	public function get_custom_help_url() {
        return 'https://wiseagent.com';
    }

	public function get_categories() {
        return [ 'wiseagent' ];
    }

	public function get_keywords() {
        return [ 'wiseagent', 'wise', 'agent' ];
    }

	public function get_script_depends() {
		return [];
	}

	public function get_style_depends() {
		return [];
	}

	protected function render() {
        $settings = $this->get_settings_for_display();

		$form_id = $settings['captureform'];
		if($form_id == 0) {
			echo 'Please select a form';
			return;
		}

		echo do_shortcode("[wiseagent form_id=$form_id]");
    }

    protected function register_controls() {

		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Content', 'wiseagent' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		// Add dropdown menu to select Wise Agent capture forms
		$this->add_control(
			'captureform',
			[
				'label' => esc_html__( 'Capture Form', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 0,
				'options' => $this->get_wa_capture_forms()
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_section',
			[
				'label' => esc_html__( 'Form Body', 'wiseagent' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		# Typography for form body
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'form_body_typography',
				'label' => esc_html__( 'Typography', 'wiseagent' ),
				'selector' => '{{WRAPPER}} .wiseagent-form',
			]
		);

		# Text color for form body
		$this->add_control(
			'form_body_text_color',
			[
				'label' => esc_html__( 'Text Color', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form' => 'color: {{VALUE}};',
				],
				'default' => '#000000',
			]
		);


		$this->add_control(
			'form_width',
			[
				'label' => esc_html__( 'Form Width', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'default' => 100,
				'min' => 0,
				'max' => 100,
				'step' => 1,
				'units' => '%',
				'selectors' => [
					'{{WRAPPER}} .elementor-widget-container' => 'width: {{VALUE}}%;',
				],
				'separator' => 'after',
			]
		);


		$this->add_control(
			'form_padding',
			[
				'label' => esc_html__( 'Form Padding', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'default' => [
					'top' => '10',
					'right' => '10',
					'bottom' => '10',
					'left' => '10',
					'unit' => 'px',
					'isLinked' => false,
				],
			]
		);

		$this->add_control(
			'form_margin',
			[
				'label' => esc_html__( 'Form Margin', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'after',
				'default' => [
					'top' => '10',
					'right' => '10',
					'bottom' => '10',
					'left' => '10',
					'unit' => 'px',
					'isLinked' => false,
				],
			]
		);

		$this->add_control(
			'form_background_color',
			[
				'label' => esc_html__( 'Background Color', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'form_border_style',
			[
				'label' => esc_html__( 'Border Style', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'none',
				'options' => [
					'solid' => esc_html__( 'Solid', 'wiseagent' ),
					'dashed' => esc_html__( 'Dashed', 'wiseagent' ),
					'dotted' => esc_html__( 'Dotted', 'wiseagent' ),
					'double' => esc_html__( 'Double', 'wiseagent' ),
					'groove' => esc_html__( 'Groove', 'wiseagent' ),
					'ridge' => esc_html__( 'Ridge', 'wiseagent' ),
					'inset' => esc_html__( 'Inset', 'wiseagent' ),
					'outset' => esc_html__( 'Outset', 'wiseagent' ),
					'none' => esc_html__( 'None', 'wiseagent' ),
				],
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form' => 'border-style: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'form_border_color',
			[
				'label' => esc_html__( 'Border Color', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'form_border_style!' => 'none',
				],
			]
		);

		$this->add_control(
			'form_border_width',
			[
				'label' => esc_html__( 'Border Width', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'default' => 1,
				'min' => 0,
				'max' => 10,
				'step' => 1,
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form' => 'border-width: {{VALUE}}px;',
				],
				'condition' => [
					'form_border_style!' => 'none',
				],
			]
		);

		$this->add_control(
			'form_border_radius',
			[
				'label' => esc_html__( 'Border Radius', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'default' => 0,
				'min' => 0,
				'max' => 20,
				'step' => 1,
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form' => 'border-radius: {{VALUE}}px;',
				],
				'separator' => 'after',
			]
		);


		// Add a control switch to enable or disable the box shadow
		$this->add_control(
			'form_box_shadow_switch',
			[
				'label' => esc_html__( 'Form Box Shadow', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Show', 'wiseagent' ),
				'label_off' => esc_html__( 'Hide', 'wiseagent' ),
				'return_value' => 'yes',
				'default' => 'no',
			]
		);

		$this->add_control(
			'form_box_shadow',
			[
				'label' => esc_html__( 'Form Box Shadow', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::BOX_SHADOW,
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form' => 'box-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{SPREAD}}px {{COLOR}};',
				],
				'separator' => 'after',
				'condition' => [
					'form_box_shadow_switch' => 'yes',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_section_submit',
			[
				'label' => esc_html__( 'Submit Button', 'wiseagent' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		// Button Dimensions
		$this->add_responsive_control(
			'form_submit_button_width',
			[
				'label' => esc_html__( 'Width', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 500,
					],
					'%' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default' => [
					'size' => 100,
					'unit' => '%',
				],
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form-submit input' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'form_submit_button_height',
			[
				'label' => esc_html__( 'Height', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 500,
					],
					'em' => [
						'min' => 0,
						'max' => 50,
					],
				],
				'default' => [
					'size' => 3,
					'unit' => 'em',
				],
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form-submit input' => 'height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		// font family for the submit button
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'form_submit_button_typography',
				'label' => esc_html__( 'Submit Button Typography', 'wiseagent' ),
				'selector' => '{{WRAPPER}} .wiseagent-form-submit input',
			]
		);

		// Font color for the submit button
		$this->add_control(
			'form_submit_button_font_color',
			[
				'label' => esc_html__( 'Text Color', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form-submit input' => 'color: {{VALUE}};',
				],
			]
		);

		# Form Submit Button Background Color
		$this->add_control(
			'form_submit_button_background_color',
			[
				'label' => esc_html__( 'Submit Button Color', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form-submit input' => 'background-color: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		// Show controls for submit button border
		$this->add_control(
			'form_submit_button_border_style',
			[
				'label' => esc_html__( 'Border Style', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'none',
				'options' => [
					'solid' => esc_html__( 'Solid', 'wiseagent' ),
					'dashed' => esc_html__( 'Dashed', 'wiseagent' ),
					'dotted' => esc_html__( 'Dotted', 'wiseagent' ),
					'double' => esc_html__( 'Double', 'wiseagent' ),
					'groove' => esc_html__( 'Groove', 'wiseagent' ),
					'ridge' => esc_html__( 'Ridge', 'wiseagent' ),
					'inset' => esc_html__( 'Inset', 'wiseagent' ),
					'outset' => esc_html__( 'Outset', 'wiseagent' ),
					'none' => esc_html__( 'None', 'wiseagent' ),
				],
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form-submit input' => 'border-style: {{VALUE}};',
				],
			]
		);

		// Add a control to set the border color
		$this->add_control(
			'form_submit_button_border_color',
			[
				'label' => esc_html__( 'Border Color', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form-submit input' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'form_submit_button_border_style!' => 'none',
				],
			]
		);

		// Add a control to set the border width
		$this->add_control(
			'form_submit_button_border_width',
			[
				'label' => esc_html__( 'Border Width', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'default' => 1,
				'min' => 1,
				'max' => 10,
				'step' => 1,
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form-submit input' => 'border-width: {{VALUE}}px;',
				],
				'condition' => [
					'form_submit_button_border_style!' => 'none',
				],
			]
		);

		// Add a control to set the border radius
		$this->add_control(
			'form_submit_button_border_radius',
			[
				'label' => esc_html__( 'Border Radius', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'default' => 5,
				'min' => 0,
				'max' => 20,
				'step' => 1,
				'unit' => 'px',
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form-submit input' => 'border-radius: {{VALUE}}px;',
				],
			]
		);

		// submit button padding
		$this->add_responsive_control(
			'form_submit_button_padding',
			[
				'label' => esc_html__( 'Padding', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form-submit input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'default' => [
					'top' => '10',
					'right' => '20',
					'bottom' => '10',
					'left' => '20',
					'unit' => 'px',
					'isLinked' => false,
				],
			]
		);

		// Add a BOX_SHADOW control for the submit button

		$this->add_control(
			'form_box_shadow_switch_submit',
			[
				'label' => esc_html__( 'Box Shadow', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Show', 'wiseagent' ),
				'label_off' => esc_html__( 'Hide', 'wiseagent' ),
				'return_value' => 'yes',
				'default' => 'no',
			]
		);

		$this->add_control(
			'form_box_shadow_submit',
			[
				'label' => esc_html__( 'Box Shadow', 'wiseagent' ),
				'type' => \Elementor\Controls_Manager::BOX_SHADOW,
				'selectors' => [
					'{{WRAPPER}} .wiseagent-form .wiseagent-form-submit input' => 'box-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{SPREAD}}px {{COLOR}};',
				],
				'separator' => 'after',
				'condition' => [
					'form_box_shadow_switch_submit' => 'yes',
				],
			]
		);
		
		$this->end_controls_section();

	}

	protected function get_wa_capture_forms() {
		$my_forms = array();
		if(!is_null($this->wa_forms_resp)) {
			foreach($this->wa_forms_resp as $f) {
				$my_forms[$f->userFormID] = $f->userFormName;
			}
			return $my_forms;
		} else {
			$wa_api = new WA_API(get_option("wiseagent_options"), get_option('wiseagent_hcaptcha_options'), get_option('wiseagent_recaptcha_options'));
			$my_forms_resp = $wa_api->get_wa_capture_forms();
			$this->wa_forms_resp = $my_forms_resp;
			foreach($my_forms_resp as $f) {
				$my_forms[$f->userFormID] = $f->userFormName;
			}
			return $my_forms;
		}
	}

	protected function content_template() {}

}