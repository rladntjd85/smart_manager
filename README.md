<h1 align="center"> Smart Manager · Gemini 2.5 AI 기반 커머스 운영 시스템 </h1>
<p align="center">
  <a href="https://smart-manager-server-154955176179.asia-northeast3.run.app/products" target="_blank">
    <img src="https://img.shields.io/badge/Live_Demo-방문하기-brightgreen?style=for-the-badge&logo=googlecloud" />
  </a>
</p>
<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php" />
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?logo=laravel" />
  <img src="https://img.shields.io/badge/Filament-v5.4-EBB308?logo=filamentphp" />
  <img src="https://img.shields.io/badge/MySQL-8.4_LTS-4479A1?logo=mysql" />
  <img src="https://img.shields.io/badge/AI-Gemini_2.5_Flash_Lite-4285F4?logo=googlegemini" />
  <img src="https://img.shields.io/badge/GCP-Cloud_Run-4285F4?logo=googlecloud" />
</p>

<p align="center">
  <b>Gemini 2.5 Flash Lite 모델을 활용한 고속 상품 데이터 자동화 엔진</b><br/>
  최신 멀티모달 AI를 통해 비정형 데이터를 실시간으로 정형화하며,<br/>
  PHP 8.3 및 MySQL 8.4 LTS 환경에서 엔터프라이즈급 안정성을 구축했습니다.
</p>

<hr/>

## 🚀 핵심 엔진: Gemini 2.5 Flash Lite 기반 분석
본 시스템은 차세대 **Gemini 2.5 Flash Lite** 모델을 탑재하여 운영 효율을 극대화했습니다.

* **초고속 멀티모달 분석:** 이미지와 텍스트를 동시에 처리하는 Flash Lite 모델 특유의 낮은 레이턴시를 활용, 상품 등록 프로세스 실시간 자동화.
* **지능형 데이터 추출:** 복잡한 상품 설명과 이미지 속 텍스트(OCR)를 분석하여 [규격, 소재, 제조국, 가격] 등의 핵심 속성을 JSON 규격으로 정규화.
* **비용 최적화 설계:** 고성능 대비 경제적인 Flash Lite 모델을 채택하여, 대량의 상품 데이터를 처리할 때의 API 운영 비용을 획기적으로 절감.
* **스마트 태깅:** 상품의 시각적 요소를 분석하여 SEO 최적화를 위한 연관 키워드를 자동으로 생성 및 매핑.

<hr/>

## 1. 프로젝트 개요
<ul>
  <li><b>사이트 주소:</b> <a href="https://smart-manager-server-154955176179.asia-northeast3.run.app">https://smart-manager-server-154955176179.asia-northeast3.run.app/</a></li>
  <li><b>테스트 계정:</b> guest@gmail.com / guest1234 </li>
  <li><b>개발 기간:</b> 2026.03.25 ~ 2026.03.27 (진행 중)</li>
  <li><b>핵심 기술 스택:</b> Filament v5.4.1, Gemini 2.5 Flash Lite</li>
  <li><b>인프라 환경:</b> Google Cloud Run, Cloud SQL (MySQL 8.4 LTS)</li>
</ul>

<hr/>

## 2. 아키텍처 및 기술 스택
<ul>
  <li><b>Backend:</b> PHP 8.3, Laravel 11.x (최신 기능을 활용한 견고한 아키텍처)</li>
  <li><b>Admin UI:</b> Filament v5 (TALL Stack 기반의 고속 어드민 구축)</li>
  <li><b>Database:</b> MySQL 8.4 LTS (AI 추출 데이터를 위한 JSON Native 지원 활용)</li>
  <li><b>Infrastructure:</b> Docker, Google Cloud Run, Artifact Registry</li>
</ul>

<hr/>

## 3. 해결한 주요 문제 (Trouble Shooting)

### 1. Gemini API 응답 구조화 및 예외 처리
<ul>
  <li><b>문제:</b> AI 응답이 간혹 예외적인 텍스트를 포함하여 DB 인서트 에러 발생.</li>
  <li><b>해결:</b> <code>Gemini 2.5</code>의 JSON Mode를 강제하고, Laravel의 유효성 검사(Validator)를 통한 데이터 2차 정제 로직 구현.</li>
</ul>

### 2. Cloud Run 8080 포트 및 헬스체크 최적화
<ul>
  <li><b>문제:</b> 배포 시 포트 미응답으로 인한 서비스 기동 실패.</li>
  <li><b>해결:</b> <code>php artisan serve</code> 단일 프로세스 아키텍처를 채택하여 구글 헬스체크 통과 및 배포 안정성 확보.</li>
</ul>

### 3. HTTPS Mixed Content 이슈 해결
<ul>
  <li><b>문제:</b> SSL Termination 환경에서 CSS/JS 리소스 로드 차단.</li>
  <li><b>해결:</b> <code>AppServiceProvider</code> 내 <code>URL::forceScheme('https')</code> 설정을 통한 보안 통신 일관성 유지.</li>
</ul>

<hr/>

## 4. 확장 가능성 (Next Step)
<ul>
  <li><b>AI 기반 자동 재고 예측:</b> 판매 추이를 Gemini가 분석하여 적정 발주량 제안 기능 추가 예정.</li>
  <li><b>GitHub Actions CI/CD:</b> 완전 자동화된 빌드/배포 파이프라인 구축.</li>
</ul>
