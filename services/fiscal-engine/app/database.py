from sqlalchemy import Engine,create_engine,text

from .settings import settings


def create_database_engine()->Engine:
    return create_engine(settings.sqlalchemy_url,pool_pre_ping=True)


def check_database_connection()->None:
    engine=create_database_engine()
    try:
        with engine.connect() as connection:
            connection.execute(text('SELECT 1'))
    finally:
        engine.dispose()
