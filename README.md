# MomoBoard

Render 테스트용으로 바로 올릴 수 있게 정리한 PHP 이미지보드입니다.

## 핵심 구조

- `public/`: 실제 웹 루트
- `src/`: 로직, 저장소, 액션, 공통 헬퍼
- `config/`: 앱 설정
- `storage/data/boards/*.json`: 테스트 단계 게시물 저장
- `public/uploads/`: 테스트 단계 이미지 저장
- `database/schema/postgresql.sql`: 실제 서비스용 DB 스키마 초안

## 왜 이 구조가 좋은가

기존 구조는 모든 게시물을 한 파일에 넣고, 보드 구분도 거의 없고, 업로드/저장 로직이 파일 여러 곳에 흩어져 있었습니다.
이 빌드는 다음 기준으로 바꿨습니다.

1. **보드별 저장 분리**: `storage/data/boards/b.json` 같은 식으로 분리
2. **뷰/로직 분리**: `public`은 진입점만, 실제 처리 로직은 `src`에 배치
3. **저장소 계층 분리**: 지금은 JSON 저장, 나중에는 PostgreSQL 저장소로 교체 가능
4. **Render 테스트 최적화**: Docker + Apache 기준으로 바로 배포 가능
5. **모바일 대응**: 720px 이하에서 작성 패널 토글형으로 전환

## Render 테스트 환경 주의

현재는 JSON 파일과 업로드 이미지를 컨테이너 로컬 파일시스템에 저장합니다.
Render 기본 웹 서비스는 **ephemeral filesystem** 이므로 재배포/재시작 시 데이터가 사라질 수 있습니다.
따라서 이 버전은 **테스트용** 입니다. Render 공식 문서도 기본 파일시스템은 영속적이지 않다고 안내합니다. 실제 서비스 단계에서는 별도 DB와 외부 스토리지를 붙여야 합니다.

## 실제 서비스로 확장할 때 권장 구조

최선의 현실적인 방향은 아래입니다.

### 1순위 권장안
- **DB**: PostgreSQL
- **이미지 저장소**: S3 호환 스토리지 (Cloudflare R2, AWS S3, Backblaze B2 등)
- **앱 서버**: 지금 구조 유지

이 방식이 가장 안전합니다. 이미지보드는 글/답글/이미지 메타데이터가 분리되어야 하고, 파일시스템 저장은 서버 이전/스케일링에 약합니다.

### 이번 테스트 빌드에서 이미 준비된 부분
- `src/Repository/PostRepositoryInterface.php`
- `src/Repository/JsonPostRepository.php`
- `src/Repository/PdoPostRepository.php`
- `database/schema/postgresql.sql`

즉, 나중에 DB를 붙일 때 전체를 다시 뜯어고치는 것이 아니라 저장소 구현을 DB 쪽으로 채우면 됩니다.

## 로컬 실행

### Docker
```bash
docker build -t momoboard .
docker run -p 8080:80 momoboard
```

브라우저에서 `http://localhost:8080`

## 배포

Render에서 새 Web Service 생성 후 이 프로젝트를 연결하면 됩니다.
`render.yaml` 포함되어 있어 Docker 기반 배포에 바로 사용할 수 있습니다.

## 파일 경로 안내

중요 파일은 아래입니다.

- 홈: `public/index.php`
- 보드 목록/스레드 목록: `public/board.php`
- 스레드 상세/답글: `public/thread.php`
- 글쓰기 처리: `public/post.php`
- 공통 설정: `config/app.php`
- 저장 구조/헬퍼: `src/Support/helpers.php`
- JSON 저장소: `src/Repository/JsonPostRepository.php`
- 스타일: `public/assets/css/style.css`
- 모바일 토글 스크립트: `public/assets/js/app.js`

## 남겨둔 현실적인 제한

- 무료 Render에서는 업로드 이미지와 JSON 데이터가 영구 보존되지 않을 수 있음
- 썸네일 생성은 넣지 않음 (서버 확장 시 이미지 처리 라이브러리 또는 별도 워커 권장)
- 관리자 기능/삭제/신고/레이트리밋은 아직 미구현

이 제한들은 **오류가 아니라 테스트 단계 범위에 맞춰 의도적으로 단순화한 것**입니다.
